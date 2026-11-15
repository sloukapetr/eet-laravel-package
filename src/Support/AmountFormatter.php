<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Support;

use SloukaPetr\Eet\Exceptions\ValidationException;

final class AmountFormatter
{
    /**
     * CastkaType is an open interval of 100_000_000 CZK, so the minor-unit
     * bounds are exclusive at 10_000_000_000 haléřů.
     */
    private const LIMIT_MINOR = 10_000_000_000;

    public static function format(int $minor, string $field = 'amount'): string
    {
        if ($minor <= -self::LIMIT_MINOR || $minor >= self::LIMIT_MINOR) {
            throw new ValidationException(
                $field,
                'Amount must be inside the open interval (-100000000, 100000000).',
            );
        }

        $negative = $minor < 0;
        $absolute = $negative ? -$minor : $minor;
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        if ($negative && $whole === 0) {
            $formatted = sprintf('-0.%02d', $fraction);
        } else {
            $formatted = sprintf('%s%d.%02d', $negative ? '-' : '', $whole, $fraction);
        }

        if (! SchemaPatterns::matches('CastkaType', $formatted)) {
            throw new ValidationException($field, 'Amount does not match CastkaType.');
        }

        return $formatted;
    }
}
