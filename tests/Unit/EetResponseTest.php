<?php

declare(strict_types=1);

use SloukaPetr\Eet\Data\EetResponse;
use SloukaPetr\Eet\Data\Warning;

it('treats a missing code, code -1 and code 8 as temporary', function (?int $code) {
    $response = new EetResponse(ok: false, code: $code);

    expect($response->isTemporaryError())->toBeTrue()
        ->and($response->isPermanentError())->toBeFalse();
})->with([
    'transport' => [null],
    'retry' => [-1],
    'technical' => [8],
]);

it('treats schema and business error codes as permanent', function (int $code) {
    $response = new EetResponse(ok: false, code: $code, message: 'rejected');

    expect($response->isPermanentError())->toBeTrue()
        ->and($response->isTemporaryError())->toBeFalse();
})->with([2, 3, 4, 6, 7]);

it('does not classify an unknown error code', function () {
    $response = new EetResponse(ok: false, code: 5);

    expect($response->isTemporaryError())->toBeFalse()
        ->and($response->isPermanentError())->toBeFalse();
});

it('does not report an error for a successful verification response', function () {
    $response = new EetResponse(
        ok: true,
        test: true,
        code: 0,
        warnings: [new Warning(code: 1, message: 'notice')],
    );

    expect($response->isTemporaryError())->toBeFalse()
        ->and($response->isPermanentError())->toBeFalse()
        ->and($response->warnings[0]->code)->toBe(1);
});
