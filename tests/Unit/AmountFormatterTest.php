<?php

declare(strict_types=1);

use SloukaPetr\Eet\Exceptions\ValidationException;
use SloukaPetr\Eet\Support\AmountFormatter;
use SloukaPetr\Eet\Support\SchemaPatterns;

it('formats minor units with two decimal places', function (int $minor, string $expected) {
    expect(AmountFormatter::format($minor))->toBe($expected)
        ->and(SchemaPatterns::matches('CastkaType', $expected))->toBeTrue();
})->with([
    'zero' => [0, '0.00'],
    'whole crowns' => [100, '1.00'],
    'halere' => [50, '0.50'],
    'negative halere' => [-150, '-1.50'],
    'negative under one crown' => [-50, '-0.50'],
    'single negative haler' => [-1, '-0.01'],
    'larger amount' => [123_456, '1234.56'],
    'just inside the positive limit' => [9_999_999_999, '99999999.99'],
    'just inside the negative limit' => [-9_999_999_999, '-99999999.99'],
]);

it('rejects an amount at the exclusive 100 million limit', function (int $minor) {
    expect(fn () => AmountFormatter::format($minor))
        ->toThrow(ValidationException::class, 'amount: Amount must be inside the open interval');
})->with([
    'positive limit' => [10_000_000_000],
    'negative limit' => [-10_000_000_000],
    'beyond the limit' => [10_000_000_001],
]);

it('names the field that failed the amount check', function () {
    try {
        AmountFormatter::format(10_000_000_000, 'totalMinor');
    } catch (ValidationException $exception) {
        expect($exception->field)->toBe('totalMinor');

        return;
    }

    test()->fail('Expected a validation exception.');
});
