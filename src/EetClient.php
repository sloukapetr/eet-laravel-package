<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use SloukaPetr\Eet\Data\EetResponse;
use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Exceptions\ValidationException;
use SloukaPetr\Eet\Support\MessageLimiter;
use Throwable;

final class EetClient
{
    /**
     * @param  array<string, mixed>  $endpoints
     */
    public function __construct(
        private readonly Factory $http,
        private readonly MessageBuilder $messages,
        private readonly Signer $signer,
        private readonly ResponseParser $parser,
        private readonly ResponseVerifier $verifier,
        private readonly string $environment,
        private readonly array $endpoints,
        private readonly int $timeout,
        private readonly bool $verifyResponseSignature,
    ) {}

    public function send(Receipt $receipt, Certificate $certificate, SendOptions $options = new SendOptions): EetResponse
    {
        $uuid = $options->uuid ?? (string) Str::uuid();
        $xml = $this->signer->sign(
            $this->messages->build($receipt, $options, $uuid, CarbonImmutable::now('UTC')),
            $certificate,
        );
        MessageLimiter::assertWithinLimit($xml);

        $started = hrtime(true);

        try {
            $response = $this->http
                ->withHeaders([
                    'Content-Type' => SoapNamespaces::CONTENT_TYPE,
                    'SOAPAction' => SoapNamespaces::SOAP_ACTION,
                ])
                ->withBody($xml, SoapNamespaces::CONTENT_TYPE)
                ->withOptions(['allow_redirects' => false])
                ->timeout($this->resolveTimeout($options))
                ->connectTimeout($this->resolveTimeout($options))
                ->post($this->endpoint());
        } catch (ConnectionException) {
            return $this->temporary($uuid, $xml, null, $started, 'The EET service did not respond.');
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            return $this->temporary($uuid, $xml, null, $started, 'The EET service did not respond.');
        }

        $body = $response->body();

        if ($response->serverError() || $response->status() === 0) {
            return $this->temporary($uuid, $xml, $body, $started, 'The EET service returned an HTTP error.');
        }

        $parsed = $this->parser->parse($body);
        $signatureValid = null;

        if ($parsed->ok && $parsed->pok !== null && $this->verifyResponseSignature) {
            $signatureValid = $this->verifier->passes($body, $this->environment);

            if (! $signatureValid) {
                return new EetResponse(
                    ok: false,
                    pok: $parsed->pok,
                    test: $parsed->test,
                    message: 'Response signature was rejected.',
                    warnings: $parsed->warnings,
                    uuid: $parsed->uuid ?? $uuid,
                    receivedAt: $parsed->receivedAt,
                    durationMs: $this->duration($started),
                    requestXml: $xml,
                    responseXml: $body,
                    signatureValid: false,
                );
            }
        }

        return new EetResponse(
            ok: $parsed->ok,
            pok: $parsed->pok,
            test: $parsed->test,
            code: $parsed->code,
            message: $parsed->message,
            warnings: $parsed->warnings,
            uuid: $parsed->uuid ?? $uuid,
            receivedAt: $parsed->receivedAt,
            durationMs: $this->duration($started),
            requestXml: $xml,
            responseXml: $body,
            signatureValid: $signatureValid,
        );
    }

    public function verify(Receipt $receipt, Certificate $certificate, SendOptions $options = new SendOptions): EetResponse
    {
        return $this->send($receipt, $certificate, new SendOptions(
            firstSend: $options->firstSend,
            verification: true,
            uuid: $options->uuid,
            timeout: $options->timeout,
        ));
    }

    private function endpoint(): string
    {
        $endpoint = $this->endpoints[$this->environment] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new ValidationException('environment', 'EET environment must be playground or production.');
        }

        return $endpoint;
    }

    private function resolveTimeout(SendOptions $options): int
    {
        return max(2, $options->timeout ?? $this->timeout);
    }

    private function duration(int|float $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }

    private function temporary(string $uuid, string $requestXml, ?string $responseXml, int|float $started, string $message): EetResponse
    {
        return new EetResponse(
            ok: false,
            message: $message,
            uuid: $uuid,
            durationMs: $this->duration($started),
            requestXml: $requestXml,
            responseXml: $responseXml,
        );
    }
}
