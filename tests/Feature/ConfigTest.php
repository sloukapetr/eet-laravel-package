<?php

declare(strict_types=1);

it('exposes the soap endpoints and the caeet servers from the downloaded specs', function () {
    $wsdl = file_get_contents(dirname(__DIR__, 2).'/docs/EETServiceSOAP.wsdl');
    $openApi = file_get_contents(dirname(__DIR__, 2).'/docs/caeetapi/caeetapi_jwt.yml');

    expect($wsdl)->toContain('https://pg.trzbyeet.gov.cz:443/eet/services/EETServiceSOAP/v4')
        ->and($wsdl)->toContain('http://fs.gov.cz/eet/OdeslaniTrzby')
        ->and(config('eet.environment'))->toBe('playground')
        ->and(config('eet.endpoints.playground'))->toBe('https://pg.trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4')
        ->and(config('eet.endpoints.production'))->toBe('https://trzbyeet.gov.cz/eet/services/EETServiceSOAP/v4')
        ->and(config('eet.timeout'))->toBe(5)
        ->and(config('eet.verify_response_signature'))->toBeTrue()
        ->and(config('eet.openssl_binary'))->toBe('openssl')
        ->and(config('eet.caeet.base_url'))->toBeNull()
        ->and(config('eet.caeet.timeout'))->toBe(10);

    foreach (config('eet.caeet.endpoints') as $url) {
        expect($openApi)->toContain($url);
    }
});
