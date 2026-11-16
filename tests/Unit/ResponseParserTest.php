<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\ResponseParser;

it('parses a confirmation, the playground flag and warnings', function () {
    $response = (new ResponseParser)->parse(responseXml(
        '<v4:Potvrzeni pok="03965780-6457-4842-bd80-5f9195c0b8c8-ff" test="true"/>'.
        '<v4:Varovani kod_varov="2">Pozde</v4:Varovani>'.
        '<v4:Varovani kod_varov="3">Dalsi</v4:Varovani>',
        'dat_prij="2026-07-01T09:02:20Z"',
    ));

    expect($response->ok)->toBeTrue()
        ->and($response->pok)->toBe('03965780-6457-4842-bd80-5f9195c0b8c8-ff')
        ->and($response->test)->toBeTrue()
        ->and($response->isTemporaryError())->toBeFalse()
        ->and($response->warnings[0]->code)->toBe(2)
        ->and($response->warnings[0]->message)->toBe('Pozde')
        ->and($response->warnings[1]->code)->toBe(3)
        ->and($response->receivedAt?->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-07-01T09:02:20Z');
});

it('parses every documented error code', function (int $code, bool $ok, bool $temporary, bool $permanent) {
    $response = (new ResponseParser)->parse(responseXml(
        '<v4:Chyba kod="'.$code.'" test="true">Popis '.$code.'</v4:Chyba>',
        'dat_odmit="2026-07-01T09:02:20Z"',
    ));

    expect($response->ok)->toBe($ok)
        ->and($response->code)->toBe($code)
        ->and($response->message)->toBe('Popis '.$code)
        ->and($response->test)->toBeTrue()
        ->and($response->isTemporaryError())->toBe($temporary)
        ->and($response->isPermanentError())->toBe($permanent)
        ->and($response->receivedAt)->toBeInstanceOf(CarbonImmutable::class);
})->with([
    'retry' => [-1, false, true, false],
    'verification ok' => [0, true, false, false],
    'encoding' => [2, false, false, true],
    'schema' => [3, false, false, true],
    'signature' => [4, false, false, true],
    'eic' => [6, false, false, true],
    'size' => [7, false, false, true],
    'technical' => [8, false, true, false],
]);

it('treats invalid response xml as a temporary error', function (string $xml) {
    $response = (new ResponseParser)->parse($xml);

    expect($response->ok)->toBeFalse()
        ->and($response->isTemporaryError())->toBeTrue()
        ->and($response->message)->not->toContain('BinarySecurityToken');
})->with([
    'broken' => ['<not-xml'],
    'empty body' => ['<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body/></soapenv:Envelope>'],
    'invalid pok' => [responseXml('<v4:Potvrzeni pok="not-a-pok"/>')],
]);

function responseXml(string $inner, string $headerAttributes = ''): string
{
    return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v4="http://fs.gov.cz/eet/schema/v4">'.
        '<soapenv:Body><v4:Odpoved><v4:Hlavicka uuid_zpravy="03965780-6457-4842-bd80-5f9195c0b8c8" '.$headerAttributes.'/>'.
        $inner.
        '</v4:Odpoved></soapenv:Body></soapenv:Envelope>';
}
