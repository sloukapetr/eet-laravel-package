<?php

declare(strict_types=1);

use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Exceptions\ValidationException;

it('accepts a version 4 uuid and a timeout of at least two seconds', function () {
    $options = new SendOptions(
        firstSend: false,
        verification: true,
        uuid: '03965780-6457-4842-bd80-5f9195c0b8c8',
        timeout: 2,
    );

    expect($options->firstSend)->toBeFalse()
        ->and($options->verification)->toBeTrue()
        ->and($options->timeout)->toBe(2);
});

it('rejects a uuid that is not version 4', function (string $uuid) {
    expect(fn () => new SendOptions(uuid: $uuid))
        ->toThrow(fn (ValidationException $exception) => $exception->field === 'uuid');
})->with([
    'not a uuid' => ['not-a-uuid'],
    'version 1' => ['03965780-6457-1842-bd80-5f9195c0b8c8'],
]);

it('rejects a timeout under two seconds', function () {
    expect(fn () => new SendOptions(timeout: 1))
        ->toThrow(fn (ValidationException $exception) => $exception->field === 'timeout');
});
