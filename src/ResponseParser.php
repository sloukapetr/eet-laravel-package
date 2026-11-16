<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use SloukaPetr\Eet\Data\EetResponse;
use SloukaPetr\Eet\Data\Warning;
use SloukaPetr\Eet\Support\SchemaPatterns;

final class ResponseParser
{
    public function parse(string $xml): EetResponse
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded !== true) {
            return $this->temporary('Response XML is not valid.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('v4', SoapNamespaces::EET);

        $responses = $xpath->query('//v4:Odpoved');
        $response = $responses === false ? null : $responses->item(0);

        if (! $response instanceof DOMElement) {
            return $this->temporary('Response XML is not valid.');
        }

        $header = $this->child($response, 'Hlavicka');
        $confirmation = $this->child($response, 'Potvrzeni');
        $error = $this->child($response, 'Chyba');
        $warnings = $this->warnings($response);

        if ($warnings === null || ($confirmation instanceof DOMElement && $error instanceof DOMElement) || (! $confirmation instanceof DOMElement && ! $error instanceof DOMElement)) {
            return $this->temporary('Response XML is not valid.');
        }

        $uuid = $header instanceof DOMElement ? $this->nullableAttribute($header, 'uuid_zpravy') : null;
        $receivedAt = $this->receivedAt($header);

        if ($confirmation instanceof DOMElement) {
            $pok = $confirmation->getAttribute('pok');

            if (! SchemaPatterns::matches('PokType', $pok)) {
                return $this->temporary('Response did not contain a valid POK.', $uuid, $receivedAt, $warnings);
            }

            return new EetResponse(
                ok: true,
                pok: $pok,
                test: $this->booleanAttribute($confirmation),
                warnings: $warnings,
                uuid: $uuid,
                receivedAt: $receivedAt,
            );
        }

        $code = $error->getAttribute('kod');

        if (! preg_match('/^-?\d+$/', $code)) {
            return $this->temporary('Response XML is not valid.', $uuid, $receivedAt, $warnings);
        }

        $numericCode = (int) $code;
        $message = trim($error->textContent);

        return new EetResponse(
            ok: $numericCode === 0,
            test: $this->booleanAttribute($error),
            code: $numericCode,
            message: $message !== '' ? $message : null,
            warnings: $warnings,
            uuid: $uuid,
            receivedAt: $receivedAt,
        );
    }

    private function child(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName && $child->namespaceURI === SoapNamespaces::EET) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<Warning>|null
     */
    private function warnings(DOMElement $response): ?array
    {
        $warnings = [];

        foreach ($response->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'Varovani' || $child->namespaceURI !== SoapNamespaces::EET) {
                continue;
            }

            $code = $child->getAttribute('kod_varov');

            if (! preg_match('/^\d+$/', $code)) {
                return null;
            }

            $warnings[] = new Warning((int) $code, trim($child->textContent) ?: null);
        }

        if (count($warnings) > 10) {
            return null;
        }

        return $warnings;
    }

    private function receivedAt(?DOMElement $header): ?CarbonImmutable
    {
        if (! $header instanceof DOMElement) {
            return null;
        }

        $value = $this->nullableAttribute($header, 'dat_prij') ?? $this->nullableAttribute($header, 'dat_odmit');

        if ($value === null || ! SchemaPatterns::matches('dateTime', $value)) {
            return null;
        }

        return CarbonImmutable::parse($value)->utc();
    }

    private function nullableAttribute(DOMElement $element, string $name): ?string
    {
        if (! $element->hasAttribute($name)) {
            return null;
        }

        $value = $element->getAttribute($name);

        return $value === '' ? null : $value;
    }

    private function booleanAttribute(DOMElement $element): ?bool
    {
        if (! $element->hasAttribute('test')) {
            return null;
        }

        return in_array(strtolower($element->getAttribute('test')), ['true', '1'], true);
    }

    /**
     * @param  list<Warning>  $warnings
     */
    private function temporary(string $message, ?string $uuid = null, ?CarbonImmutable $receivedAt = null, array $warnings = []): EetResponse
    {
        return new EetResponse(
            ok: false,
            message: $message,
            warnings: $warnings,
            uuid: $uuid,
            receivedAt: $receivedAt,
        );
    }
}
