<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Certificate;
use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * @param  array<string, mixed>  $overrides
 */
function receipt(array $overrides = []): Receipt
{
    $values = array_merge([
        'eic' => 'CZ00000019',
        'unitId' => 303,
        'deviceId' => '/5604/MA65',
        'serial' => '00/2224/SO57',
        'soldAt' => CarbonImmutable::parse('2026-07-01T09:02:18Z'),
        'totalMinor' => 18_858_000,
        'prepaidTopUpMinor' => 2_500,
        'prepaidDrawMinor' => 30_200,
    ], $overrides);

    return new Receipt(
        eic: $values['eic'],
        unitId: $values['unitId'],
        deviceId: $values['deviceId'],
        serial: $values['serial'],
        soldAt: $values['soldAt'],
        totalMinor: $values['totalMinor'],
        prepaidTopUpMinor: $values['prepaidTopUpMinor'],
        prepaidDrawMinor: $values['prepaidDrawMinor'],
        principalEic: $values['principalEic'] ?? null,
        multipleTaxpayers: $values['multipleTaxpayers'] ?? null,
    );
}

function playgroundCertificate(): Certificate
{
    static $certificate = null;

    if ($certificate instanceof Certificate) {
        return $certificate;
    }

    $password = getenv('EET_PLAYGROUND_P12_PASSWORD');

    if (! is_string($password) || $password === '') {
        throw new RuntimeException('Set EET_PLAYGROUND_P12_PASSWORD in .env.testing.');
    }

    $binary = file_get_contents(dirname(__DIR__).'/tests/fixtures/playground/CA_EET-Playground-CZ00000019.p12');

    if ($binary === false) {
        throw new RuntimeException('Missing playground certificate fixture.');
    }

    return $certificate = Certificate::fromPkcs12($binary, $password);
}
