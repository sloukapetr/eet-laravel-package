<?php

declare(strict_types=1);

use SloukaPetr\Eet\Facades\Eet;

it('calls the playground only when EET_PLAYGROUND_TEST=1', function () {
    if (getenv('EET_PLAYGROUND_TEST') !== '1') {
        $this->markTestSkipped('Set EET_PLAYGROUND_TEST=1 to call the playground.');
    }

    $verification = Eet::verify(receipt(), playgroundCertificate());
    $live = Eet::send(receipt(), playgroundCertificate());

    expect($verification->code)->toBe(0)
        ->and($verification->ok)->toBeTrue()
        ->and($live->ok)->toBeTrue()
        ->and($live->pok)->toEndWith('-ff')
        ->and($live->test)->toBeTrue();
});
