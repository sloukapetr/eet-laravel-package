<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Facades\Eet;
use SloukaPetr\Eet\Signer;
use SloukaPetr\Eet\SoapNamespaces;

it('sends a new uuid on every call and keeps a supplied uuid', function () {
    Http::fake([
        'pg.trzbyeet.gov.cz/*' => Http::response(errorResponse(-1), 200),
    ]);

    $first = Eet::send(receipt(), playgroundCertificate());
    $second = Eet::send(receipt(), playgroundCertificate(), new SendOptions(
        uuid: '03965780-6457-4842-bd80-5f9195c0b8c8',
    ));

    $uuids = [];

    Http::assertSent(function ($request) use (&$uuids) {
        preg_match('/uuid_zpravy="([^"]+)"/', $request->body(), $matches);
        $uuids[] = $matches[1] ?? '';

        return $request->hasHeader('SOAPAction', SoapNamespaces::SOAP_ACTION)
            && $request->hasHeader('Content-Type', SoapNamespaces::CONTENT_TYPE)
            && str_contains($request->url(), '/eet/services/EETServiceSOAP/v4');
    });

    expect($uuids)->toHaveCount(2)
        ->and($uuids[0])->not->toBe($uuids[1])
        ->and($uuids[1])->toBe('03965780-6457-4842-bd80-5f9195c0b8c8')
        ->and($first->uuid)->toBe($uuids[0])
        ->and($first->isTemporaryError())->toBeTrue()
        ->and($second->uuid)->toBe('03965780-6457-4842-bd80-5f9195c0b8c8');
});

it('returns a temporary error when the connection times out or the server fails', function (int $status) {
    if ($status === 0) {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });
    } else {
        Http::fake([
            'pg.trzbyeet.gov.cz/*' => Http::response('unavailable', $status),
        ]);
    }

    $response = Eet::send(receipt(), playgroundCertificate());

    expect($response->ok)->toBeFalse()
        ->and($response->isTemporaryError())->toBeTrue()
        ->and($response->requestXml)->not->toBeNull();
})->with([
    'timeout' => [0],
    'server error' => [503],
]);

it('accepts a playground confirmation signed by I.CA', function () {
    $signed = file_get_contents(dirname(__DIR__).'/fixtures/playground-potvrzeni.xml');

    Http::fake([
        '*' => Http::response(is_string($signed) ? $signed : '', 200),
    ]);

    $response = Eet::send(receipt(), playgroundCertificate());

    expect($response->ok)->toBeTrue()
        ->and($response->signatureValid)->toBeTrue()
        ->and($response->pok)->toEndWith('-ff')
        ->and($response->test)->toBeTrue();
});

it('rejects a confirmation signed by the taxpayer certificate', function () {
    Http::fake([
        '*' => Http::response(signedConfirmation(), 200),
    ]);

    $response = Eet::send(receipt(), playgroundCertificate());

    expect($response->ok)->toBeFalse()
        ->and($response->signatureValid)->toBeFalse()
        ->and($response->message)->toBe('Response signature was rejected.');
});

it('records fake responses without calling the network', function () {
    Http::fake([
        '*' => function () {
            throw new RuntimeException('The fake must not send HTTP.');
        },
    ]);

    $fake = Eet::fake();
    $fake->assertNothingSent();

    $fake->ok('03965780-6457-4842-bd80-5f9195c0b8c8-ff', test: true)
        ->error(-1)
        ->error(3, 'schema')
        ->timeout()
        ->warning(2, 'Pozde');

    $certificate = playgroundCertificate();
    $confirmed = Eet::send(receipt(), $certificate);
    $retried = Eet::send(receipt(), $certificate);
    $rejected = Eet::send(receipt(), $certificate);
    $timedOut = Eet::send(receipt(), $certificate);
    $warned = Eet::verify(receipt(), $certificate);

    $fake->assertSentCount(5);
    $fake->assertSent(fn ($sale, $options) => $sale->eic === 'CZ00000019' && $options->verification === true);

    expect($confirmed->ok)->toBeTrue()
        ->and($confirmed->test)->toBeTrue()
        ->and($retried->isTemporaryError())->toBeTrue()
        ->and($rejected->isPermanentError())->toBeTrue()
        ->and($rejected->code)->toBe(3)
        ->and($timedOut->isTemporaryError())->toBeTrue()
        ->and($warned->ok)->toBeTrue()
        ->and($warned->warnings[0]->code)->toBe(2);
});

function errorResponse(int $code): string
{
    return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v4="http://fs.gov.cz/eet/schema/v4"><soapenv:Body><v4:Odpoved><v4:Hlavicka/><v4:Chyba kod="'.$code.'">chyba</v4:Chyba></v4:Odpoved></soapenv:Body></soapenv:Envelope>';
}

function signedConfirmation(): string
{
    $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v4="http://fs.gov.cz/eet/schema/v4"><soapenv:Header/><soapenv:Body><v4:Odpoved><v4:Hlavicka uuid_zpravy="03965780-6457-4842-bd80-5f9195c0b8c8" dat_prij="2026-07-01T09:02:20Z"/><v4:Potvrzeni pok="03965780-6457-4842-bd80-5f9195c0b8c8-ff" test="true"/></v4:Odpoved></soapenv:Body></soapenv:Envelope>';
    $document = new DOMDocument;
    $document->loadXML($xml);

    return (new Signer)->sign($document, playgroundCertificate());
}
