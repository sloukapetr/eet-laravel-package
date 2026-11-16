<?php

declare(strict_types=1);

use SloukaPetr\Eet\ResponseVerifier;

it('accepts a playground confirmation signed by the documented I.CA issuer', function () {
    $xml = file_get_contents(dirname(__DIR__, 2).'/tests/fixtures/playground-potvrzeni.xml');
    $verifier = new ResponseVerifier;

    expect($xml)->toBeString()
        ->and($verifier->passes((string) $xml, 'playground'))->toBeTrue()
        ->and($verifier->passes((string) $xml, 'production'))->toBeTrue()
        ->and($verifier->passes((string) $xml, 'other'))->toBeFalse();
});
