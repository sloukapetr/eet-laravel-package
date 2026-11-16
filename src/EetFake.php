<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use LogicException;
use PHPUnit\Framework\Assert;
use SloukaPetr\Eet\Data\EetResponse;
use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Data\Warning;

final class EetFake
{
    /**
     * @var list<array{receipt: Receipt, certificate: Certificate, options: SendOptions}>
     */
    private array $sent = [];

    /**
     * @var list<EetResponse>
     */
    private array $responses = [];

    public function ok(string $pok, bool $test = false): self
    {
        $this->responses[] = new EetResponse(ok: true, pok: $pok, test: $test);

        return $this;
    }

    public function error(int $code, string $message = ''): self
    {
        $this->responses[] = new EetResponse(
            ok: $code === 0,
            code: $code,
            message: $message !== '' ? $message : null,
        );

        return $this;
    }

    public function timeout(): self
    {
        $this->responses[] = new EetResponse(
            ok: false,
            message: 'The EET service did not respond.',
        );

        return $this;
    }

    public function warning(int $code, string $message = ''): self
    {
        $this->responses[] = new EetResponse(
            ok: true,
            pok: '03965780-6457-4842-bd80-5f9195c0b8c8-ff',
            warnings: [new Warning($code, $message !== '' ? $message : null)],
        );

        return $this;
    }

    public function send(Receipt $receipt, Certificate $certificate, SendOptions $options = new SendOptions): EetResponse
    {
        $this->sent[] = [
            'receipt' => $receipt,
            'certificate' => $certificate,
            'options' => $options,
        ];

        $response = array_shift($this->responses);

        if (! $response instanceof EetResponse) {
            throw new LogicException('No fake EET response was queued.');
        }

        return $response;
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

    public function assertSent(callable $callback): void
    {
        $matched = false;

        foreach ($this->sent as $sent) {
            if ($callback($sent['receipt'], $sent['options'], $sent['certificate']) === true) {
                $matched = true;
                break;
            }
        }

        Assert::assertTrue($matched, 'No matching EET receipt was sent.');
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent);
    }

    public function assertNothingSent(): void
    {
        Assert::assertCount(0, $this->sent);
    }
}
