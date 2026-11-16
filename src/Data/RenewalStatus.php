<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Data;

final readonly class RenewalStatus
{
    public function __construct(
        public string $reqId,
        public string $state,
        public ?int $pollAfterSeconds = null,
        public ?string $rejectedReason = null,
    ) {}
}
