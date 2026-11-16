<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use LogicException;
use PHPUnit\Framework\Assert;
use SloukaPetr\Eet\Data\IssuedCertificate;
use SloukaPetr\Eet\Data\RenewalStatus;

final class CaeetFake
{
    public ?Certificate $certificate = null;

    /**
     * @var list<array{method: string, reqId: ?string}>
     */
    private array $calls = [];

    /**
     * @var list<string|RenewalStatus|IssuedCertificate|list<string>>
     */
    private array $responses = [];

    public function using(Certificate $certificate): self
    {
        $this->certificate = $certificate;

        return $this;
    }

    public function queueRenew(string $reqId): self
    {
        $this->responses[] = $reqId;

        return $this;
    }

    public function queueStatus(RenewalStatus $status): self
    {
        $this->responses[] = $status;

        return $this;
    }

    public function queueDownload(IssuedCertificate $certificate): self
    {
        $this->responses[] = $certificate;

        return $this;
    }

    /**
     * @param  list<string>  $reqIds
     */
    public function queueNotFinished(array $reqIds): self
    {
        $this->responses[] = $reqIds;

        return $this;
    }

    public function renew(): string
    {
        $this->calls[] = ['method' => 'renew', 'reqId' => null];
        $response = $this->shift();

        if (! is_string($response)) {
            throw new LogicException('No fake CAEET renewal was queued.');
        }

        return $response;
    }

    public function status(string $reqId): RenewalStatus
    {
        $this->calls[] = ['method' => 'status', 'reqId' => $reqId];
        $response = $this->shift();

        if (! $response instanceof RenewalStatus) {
            throw new LogicException('No fake CAEET status was queued.');
        }

        return $response;
    }

    public function claimDownload(string $reqId): IssuedCertificate
    {
        $this->calls[] = ['method' => 'claimDownload', 'reqId' => $reqId];
        $response = $this->shift();

        if (! $response instanceof IssuedCertificate) {
            throw new LogicException('No fake CAEET certificate was queued.');
        }

        return $response;
    }

    public function ackDownload(string $reqId): void
    {
        $this->calls[] = ['method' => 'ackDownload', 'reqId' => $reqId];
    }

    /**
     * @return list<string>
     */
    public function notFinished(): array
    {
        $this->calls[] = ['method' => 'notFinished', 'reqId' => null];
        $response = $this->shift();

        if (! is_array($response)) {
            throw new LogicException('No fake CAEET request list was queued.');
        }

        /** @var list<string> $response */
        return $response;
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->calls);
    }

    public function assertSent(string $method, ?string $reqId = null): void
    {
        Assert::assertContains(['method' => $method, 'reqId' => $reqId], $this->calls);
    }

    private function shift(): mixed
    {
        if ($this->responses === []) {
            throw new LogicException('No fake CAEET response was queued.');
        }

        return array_shift($this->responses);
    }
}
