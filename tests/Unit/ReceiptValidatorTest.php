<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Exceptions\ValidationException;

it('accepts a receipt taken from the official sample attributes', function () {
    expect(receipt()->uniqueKey())->toBe(
        'CZ00000019|303|/5604/MA65|00/2224/SO57|2026-07-01T09:02:18Z|188580.00',
    );
});

it('builds the same unique key for the same instant in another timezone', function () {
    $prague = receipt([
        'soldAt' => CarbonImmutable::parse('2026-07-01T11:02:18+02:00'),
    ]);

    expect($prague->uniqueKey())->toBe(receipt()->uniqueKey());
});

it('rejects an evidence number that is not CZEICType', function (string $eic) {
    expect(fn () => receipt(['eic' => $eic]))
        ->toThrow(fn (ValidationException $exception) => $exception->field === 'eic');
})->with([
    'too short' => ['CZ1234567'],
    'too long' => ['CZ12345678901'],
    'missing prefix' => ['00000019'],
    'lower case prefix' => ['cz00000019'],
]);

it('accepts evidence numbers of eight, nine and ten digits', function (string $eic) {
    expect(receipt(['eic' => $eic])->eic)->toBe($eic);
})->with([
    'eight' => ['CZ00000019'],
    'nine' => ['CZ683555118'],
    'ten' => ['CZ8551015704'],
]);

it('rejects a unit id outside IdEvidJednotkyType', function (int $unitId) {
    expect(fn () => receipt(['unitId' => $unitId]))
        ->toThrow(fn (ValidationException $exception) => $exception->field === 'unitId');
})->with([
    'zero' => [0],
    'negative' => [-1],
    'above the max' => [1_000_000_000],
]);

it('rejects a device id or serial outside the schema character set or length', function (string $field, string $value) {
    expect(fn () => receipt([$field => $value]))
        ->toThrow(fn (ValidationException $exception) => $exception->field === $field);
})->with([
    'empty device' => ['deviceId', ''],
    'device too long' => ['deviceId', str_repeat('A', 21)],
    'device with a diacritic' => ['deviceId', 'pokladna-č'],
    'empty serial' => ['serial', ''],
    'serial too long' => ['serial', str_repeat('1', 26)],
]);

it('accepts the longest allowed device id and serial', function () {
    $sale = receipt([
        'deviceId' => str_repeat('A', 20),
        'serial' => str_repeat('B', 25),
    ]);

    expect($sale->deviceId)->toHaveLength(20)
        ->and($sale->serial)->toHaveLength(25);
});

it('rejects an optional principal evidence number with the same pattern', function () {
    expect(fn () => receipt(['principalEic' => 'CZ1']))
        ->toThrow(fn (ValidationException $exception) => $exception->field === 'principalEic');
});

it('rejects a prepaid amount on the limit and names that field', function () {
    expect(fn () => receipt(['prepaidTopUpMinor' => 10_000_000_000]))
        ->toThrow(fn (ValidationException $exception) => $exception->field === 'prepaidTopUpMinor');
});
