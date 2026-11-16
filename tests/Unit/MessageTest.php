<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Exceptions\ValidationException;
use SloukaPetr\Eet\MessageBuilder;
use SloukaPetr\Eet\ResponseVerifier;
use SloukaPetr\Eet\Signer;
use SloukaPetr\Eet\SoapNamespaces;
use SloukaPetr\Eet\Support\MessageLimiter;

beforeEach(function () {
    Carbon\Carbon::setTestNow(CarbonImmutable::parse('2026-07-01T09:02:18Z'));
});

afterEach(function () {
    Carbon\Carbon::setTestNow();
});

it('builds a signed message that matches the schema and the official samples', function () {
    $xml = signedReceipt(new SendOptions);

    $document = loadXml($xml);
    $trzba = $document->getElementsByTagNameNS(SoapNamespaces::EET, 'Trzba')->item(0);
    $schemaDocument = new DOMDocument;
    expect($trzba)->toBeInstanceOf(DOMElement::class);

    if (! $trzba instanceof DOMElement) {
        return;
    }

    $schemaDocument->appendChild($schemaDocument->importNode($trzba, true));
    $previous = libxml_use_internal_errors(true);
    $valid = $schemaDocument->schemaValidate(dirname(__DIR__, 2).'/docs/EETXMLSchema.xsd');
    $errors = array_map(fn ($error) => trim($error->message), libxml_get_errors());
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    expect($valid)->toBeTrue()->and($errors)->toBe([]);

    $security = $document->getElementsByTagNameNS(SoapNamespaces::WSSE, 'Security')->item(0);
    expect($security)->toBeInstanceOf(DOMElement::class);

    if (! $security instanceof DOMElement) {
        return;
    }

    $children = [];

    foreach ($security->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $children[] = $child->localName;
        }
    }

    $body = $document->getElementsByTagNameNS(SoapNamespaces::SOAP, 'Body')->item(0);
    $references = $document->getElementsByTagNameNS(SoapNamespaces::DS, 'Reference');

    expect($children)->toBe(['BinarySecurityToken', 'Signature'])
        ->and($document->getElementsByTagName('Timestamp'))->toHaveCount(0)
        ->and($document->getElementsByTagNameNS(SoapNamespaces::DS, 'Reference'))->toHaveCount(1)
        ->and($body)->toBeInstanceOf(DOMElement::class)
        ->and($references->item(0)?->getAttribute('URI'))->toBe('#'.$body?->getAttributeNS(SoapNamespaces::WSU, 'Id'))
        ->and(attribute($document, 'CanonicalizationMethod', 'Algorithm'))->toBe(SoapNamespaces::EXC_C14N)
        ->and(attribute($document, 'SignatureMethod', 'Algorithm'))->toBe(SoapNamespaces::SIGNATURE_METHOD)
        ->and(attribute($document, 'Transform', 'Algorithm'))->toBe(SoapNamespaces::EXC_C14N)
        ->and(attribute($document, 'DigestMethod', 'Algorithm'))->toBe(SoapNamespaces::DIGEST)
        ->and((new ResponseVerifier)->passes($xml, 'playground'))->toBeTrue()
        ->and((new ResponseVerifier)->passes($xml, 'production'))->toBeFalse()
        ->and(strlen($xml) < MessageLimiter::MAX_BYTES)->toBeTrue();

    $data = $document->getElementsByTagNameNS(SoapNamespaces::EET, 'Data')->item(0);
    $header = $document->getElementsByTagNameNS(SoapNamespaces::EET, 'Hlavicka')->item(0);

    expect($data)->toBeInstanceOf(DOMElement::class)
        ->and($header)->toBeInstanceOf(DOMElement::class);

    if (! $data instanceof DOMElement || ! $header instanceof DOMElement) {
        return;
    }

    expect($data->getAttribute('celk_trzba'))->toBe('188580.00')
        ->and($data->getAttribute('cerp_zuct'))->toBe('302.00')
        ->and($data->getAttribute('urceno_cerp_zuct'))->toBe('25.00')
        ->and($data->getAttribute('eic_popl'))->toBe('CZ00000019')
        ->and($data->getAttribute('id_jednotky'))->toBe('303')
        ->and($data->getAttribute('id_pokl'))->toBe('/5604/MA65')
        ->and($data->getAttribute('porad_cis'))->toBe('00/2224/SO57')
        ->and($data->getAttribute('dat_trzby'))->toBe('2026-07-01T09:02:18Z')
        ->and($data->hasAttribute('eic_poverujiciho'))->toBeFalse()
        ->and($header->getAttribute('prvni_zaslani'))->toBe('true')
        ->and($header->getAttribute('overeni'))->toBe('false')
        ->and($header->getAttribute('dat_odesl'))->toBe('2026-07-01T09:02:18Z');
});

it('includes the principal and the verification flag when they are set', function () {
    $document = loadXml(signedReceipt(new SendOptions(firstSend: false, verification: true), [
        'principalEic' => 'CZ683555118',
        'multipleTaxpayers' => true,
        'prepaidTopUpMinor' => null,
        'prepaidDrawMinor' => null,
    ]));
    $data = $document->getElementsByTagNameNS(SoapNamespaces::EET, 'Data')->item(0);
    $header = $document->getElementsByTagNameNS(SoapNamespaces::EET, 'Hlavicka')->item(0);

    expect($data)->toBeInstanceOf(DOMElement::class)
        ->and($header)->toBeInstanceOf(DOMElement::class);

    if (! $data instanceof DOMElement || ! $header instanceof DOMElement) {
        return;
    }

    expect($data->getAttribute('eic_poverujiciho'))->toBe('CZ683555118')
        ->and($data->getAttribute('povereni_vice_popl'))->toBe('true')
        ->and($data->hasAttribute('cerp_zuct'))->toBeFalse()
        ->and($header->getAttribute('prvni_zaslani'))->toBe('false')
        ->and($header->getAttribute('overeni'))->toBe('true');
});

it('rejects a soap message larger than 12 kB', function () {
    expect(fn () => MessageLimiter::assertWithinLimit(str_repeat('a', MessageLimiter::MAX_BYTES + 1)))
        ->toThrow(ValidationException::class, 'message: SOAP message exceeds 12 kB.');
});

function signedReceipt(SendOptions $options, array $overrides = []): string
{
    $document = (new MessageBuilder)->build(receipt($overrides), $options, '03965780-6457-4842-bd80-5f9195c0b8c8', CarbonImmutable::now('UTC'));

    return (new Signer)->sign($document, playgroundCertificate());
}

function loadXml(string $xml): DOMDocument
{
    $document = new DOMDocument;
    expect($document->loadXML($xml))->toBeTrue();

    return $document;
}

function attribute(DOMDocument $document, string $element, string $name): string
{
    $node = $document->getElementsByTagNameNS(SoapNamespaces::DS, $element)->item(0);

    return $node instanceof DOMElement ? $node->getAttribute($name) : '';
}
