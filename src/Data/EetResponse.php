<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Data;

use Carbon\CarbonImmutable;

final readonly class EetResponse
{
    /**
     * Codes 2, 3, 4, 6 and 7 are rejected by the schema or the business rules
     * and must not be retried unchanged. Code 8 stays temporary: the
     * application decides when a repeated technical error becomes permanent.
     *
     * @var list<int>
     */
    private const PERMANENT_CODES = [2, 3, 4, 6, 7];

    /**
     * @param  list<Warning>  $warnings
     */
    public function __construct(
        public bool $ok,
        public ?string $pok = null,
        public ?bool $test = null,
        public ?int $code = null,
        public ?string $message = null,
        public array $warnings = [],
        public ?string $uuid = null,
        public ?CarbonImmutable $receivedAt = null,
        public ?int $durationMs = null,
        public ?string $requestXml = null,
        public ?string $responseXml = null,
    ) {}

    public function isTemporaryError(): bool
    {
        if ($this->ok) {
            return false;
        }

        return $this->code === null || $this->code === -1 || $this->code === 8;
    }

    public function isPermanentError(): bool
    {
        if ($this->ok) {
            return false;
        }

        return in_array($this->code, self::PERMANENT_CODES, true);
    }
}
