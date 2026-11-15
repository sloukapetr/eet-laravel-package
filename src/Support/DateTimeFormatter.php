<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Support;

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Exceptions\ValidationException;

final class DateTimeFormatter
{
    public static function format(CarbonImmutable $instant, string $field = 'soldAt'): string
    {
        $formatted = $instant->utc()->format('Y-m-d\TH:i:s\Z');

        if (! SchemaPatterns::matches('dateTime', $formatted)) {
            throw new ValidationException($field, 'Timestamp does not match the EET dateTime pattern.');
        }

        return $formatted;
    }
}
