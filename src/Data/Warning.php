<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Data;

final readonly class Warning
{
    public function __construct(
        public int $code,
        public ?string $message = null,
    ) {}
}
