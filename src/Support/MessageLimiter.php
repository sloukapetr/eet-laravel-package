<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Support;

use SloukaPetr\Eet\Exceptions\ValidationException;

final class MessageLimiter
{
    public const MAX_BYTES = 12 * 1024;

    public static function assertWithinLimit(string $xml): void
    {
        if (strlen($xml) > self::MAX_BYTES) {
            throw new ValidationException('message', 'SOAP message exceeds 12 kB.');
        }
    }
}
