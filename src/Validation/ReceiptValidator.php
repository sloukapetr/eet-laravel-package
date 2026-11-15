<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Validation;

use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Exceptions\ValidationException;
use SloukaPetr\Eet\Support\AmountFormatter;
use SloukaPetr\Eet\Support\DateTimeFormatter;
use SloukaPetr\Eet\Support\SchemaPatterns;

final class ReceiptValidator
{
    public static function validate(Receipt $receipt): void
    {
        self::assertEic($receipt->eic, 'eic');

        if ($receipt->principalEic !== null) {
            self::assertEic($receipt->principalEic, 'principalEic');
        }

        self::assertUnitId($receipt->unitId);
        self::assertToken($receipt->deviceId, 'string20', 'deviceId');
        self::assertToken($receipt->serial, 'string25', 'serial');
        DateTimeFormatter::format($receipt->soldAt, 'soldAt');
        AmountFormatter::format($receipt->totalMinor, 'totalMinor');

        if ($receipt->prepaidTopUpMinor !== null) {
            AmountFormatter::format($receipt->prepaidTopUpMinor, 'prepaidTopUpMinor');
        }

        if ($receipt->prepaidDrawMinor !== null) {
            AmountFormatter::format($receipt->prepaidDrawMinor, 'prepaidDrawMinor');
        }
    }

    private static function assertEic(string $value, string $field): void
    {
        if (! SchemaPatterns::matches('CZEICType', $value)) {
            throw new ValidationException($field, 'Value does not match CZEICType (CZ and 8 to 10 digits).');
        }
    }

    private static function assertUnitId(int $unitId): void
    {
        if (! SchemaPatterns::matches('IdEvidJednotkyType', (string) $unitId)) {
            throw new ValidationException('unitId', 'Value does not match IdEvidJednotkyType (1 to 999999999).');
        }
    }

    private static function assertToken(string $value, string $type, string $field): void
    {
        if (! SchemaPatterns::matches($type, $value)) {
            throw new ValidationException($field, 'Value does not match '.$type.'.');
        }
    }
}
