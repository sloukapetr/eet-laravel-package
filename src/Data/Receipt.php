<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Data;

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Support\AmountFormatter;
use SloukaPetr\Eet\Support\DateTimeFormatter;
use SloukaPetr\Eet\Validation\ReceiptValidator;

final readonly class Receipt
{
    public function __construct(
        public string $eic,
        public int $unitId,
        public string $deviceId,
        public string $serial,
        public CarbonImmutable $soldAt,
        public int $totalMinor,
        public ?int $prepaidTopUpMinor = null,
        public ?int $prepaidDrawMinor = null,
        public ?string $principalEic = null,
        public ?bool $multipleTaxpayers = null,
    ) {
        ReceiptValidator::validate($this);
    }

    /**
     * Identity of one sale: eic_popl|id_jednotky|id_pokl|porad_cis|dat_trzby|celk_trzba.
     */
    public function uniqueKey(): string
    {
        return implode('|', [
            $this->eic,
            (string) $this->unitId,
            $this->deviceId,
            $this->serial,
            DateTimeFormatter::format($this->soldAt),
            AmountFormatter::format($this->totalMinor),
        ]);
    }
}
