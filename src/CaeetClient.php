<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use SloukaPetr\Eet\Data\IssuedCertificate;
use SloukaPetr\Eet\Data\RenewalStatus;
use SloukaPetr\Eet\Exceptions\CaeetException;
use SloukaPetr\Eet\Exceptions\CertificateExpired;
use SloukaPetr\Eet\Exceptions\InvalidCertificate;
use SloukaPetr\Eet\Exceptions\InvalidCertificatePassword;
use SloukaPetr\Eet\Support\CaeetJwt;
use Throwable;

final class CaeetClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly Certificate $certificate,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function renew(): string
    {
        $response = $this->send('post', '/request/renew');
        $reqId = $this->json($response)['reqId'] ?? null;

        if (! is_string($reqId) || $reqId === '') {
            throw new CaeetException('CAEET did not return a request id.');
        }

        return $reqId;
    }

    public function status(string $reqId): RenewalStatus
    {
        $response = $this->send('get', $this->requestPath($reqId, '/status'));
        $body = $this->json($response);
        $state = $body['state'] ?? null;

        if (! is_string($body['reqId'] ?? null) || ! is_string($state)) {
            throw new CaeetException('CAEET status response could not be read.');
        }

        $poll = $body['pollAfterSeconds'] ?? null;

        if (! is_int($poll)) {
            $header = $response->header('Retry-After');
            $poll = is_numeric($header) ? (int) $header : null;
        }

        return new RenewalStatus(
            reqId: $body['reqId'],
            state: $state,
            pollAfterSeconds: $poll,
            rejectedReason: is_string($body['rejectedReason'] ?? null) ? $body['rejectedReason'] : null,
        );
    }

    public function claimDownload(string $reqId): IssuedCertificate
    {
        $body = $this->json($this->send('post', $this->requestPath($reqId, '/claim-download')));
        $encoded = $body['pkcs12'] ?? null;
        $password = $body['password'] ?? null;
        $certificate = is_array($body['certificate'] ?? null) ? $body['certificate'] : [];

        if (! is_string($encoded) || ! is_string($password) || ! is_string($certificate['sn'] ?? null) || ! is_string($certificate['state'] ?? null)) {
            throw new CaeetException('CAEET certificate response could not be read.');
        }

        $binary = base64_decode($encoded, true);

        if ($binary === false || $binary === '') {
            throw new CaeetException('CAEET certificate response could not be read.');
        }

        return new IssuedCertificate($binary, $password, $certificate['sn'], $certificate['state']);
    }

    public function ackDownload(string $reqId): void
    {
        $this->send('post', $this->requestPath($reqId, '/ack-download'), [204]);
    }

    /**
     * @return list<string>
     */
    public function notFinished(): array
    {
        $data = $this->json($this->send('get', '/request/not-finished'))['data'] ?? null;

        if (! is_array($data)) {
            throw new CaeetException('CAEET request list could not be read.');
        }

        $ids = [];

        foreach ($data as $reqId) {
            if (! is_string($reqId) || $reqId === '') {
                throw new CaeetException('CAEET request list could not be read.');
            }

            $ids[] = $reqId;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $ok
     */
    private function send(string $method, string $path, array $ok = [200, 201]): Response
    {
        if ($this->baseUrl === '') {
            throw new CaeetException('CAEET base URL is not configured.');
        }

        try {
            $response = $this->http
                ->withToken(CaeetJwt::issue($this->certificate))
                ->acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->timeout($this->timeout)
                ->connectTimeout($this->timeout)
                ->send($method, $this->baseUrl.$path);
        } catch (CertificateExpired|InvalidCertificate|InvalidCertificatePassword $exception) {
            throw $exception;
        } catch (ConnectionException) {
            throw new CaeetException('CAEET request failed.');
        } catch (Throwable) {
            throw new CaeetException('CAEET request failed.');
        }

        if (! in_array($response->status(), $ok, true)) {
            throw new CaeetException($this->safeMessage($response));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new CaeetException('CAEET response could not be read.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function requestPath(string $reqId, string $suffix): string
    {
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $reqId) !== 1) {
            throw new CaeetException('CAEET request id is not a UUID.');
        }

        return '/request/'.$reqId.$suffix;
    }

    private function safeMessage(Response $response): string
    {
        $decoded = $response->json();

        if (! is_array($decoded) || isset($decoded['pkcs12'])) {
            return 'CAEET request failed.';
        }

        $message = $decoded['message'] ?? null;
        $password = $decoded['password'] ?? null;

        if (array_key_exists('pkcs12', $decoded) || array_key_exists('password', $decoded)) {
            return 'CAEET request failed.';
        }

        if (! is_string($message) || $message === '' || str_contains($message, 'BEGIN ') || strlen($message) > 200 || preg_match('/^[A-Za-z0-9]{8}$/', $message) === 1) {
            return 'CAEET request failed.';
        }

        if (is_string($password) && $password !== '' && str_contains($message, $password)) {
            return 'CAEET request failed.';
        }

        return $message;
    }
}
