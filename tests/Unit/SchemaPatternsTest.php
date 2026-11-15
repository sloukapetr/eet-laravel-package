<?php

declare(strict_types=1);

use SloukaPetr\Eet\Support\SchemaPatterns;

it('keeps every XSD pattern facet equal to docs/EETXMLSchema.xsd', function () {
    $document = new DOMDocument;
    expect($document->load(dirname(__DIR__, 2).'/docs/EETXMLSchema.xsd'))->toBeTrue();

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

    $patterns = [];

    foreach ($xpath->query('//xs:simpleType[@name]') ?: [] as $type) {
        if (! $type instanceof DOMElement) {
            continue;
        }

        $pattern = $xpath->query('./xs:restriction/xs:pattern', $type)->item(0);

        if (! $pattern instanceof DOMElement) {
            continue;
        }

        $patterns[$type->getAttribute('name')] = $pattern->getAttribute('value');
    }

    expect($patterns)->toBe(SchemaPatterns::TYPES)
        ->and(facet($xpath, 'CastkaType', 'minExclusive'))->toBe(SchemaPatterns::AMOUNT_MIN_EXCLUSIVE)
        ->and(facet($xpath, 'CastkaType', 'maxExclusive'))->toBe(SchemaPatterns::AMOUNT_MAX_EXCLUSIVE)
        ->and(facet($xpath, 'IdEvidJednotkyType', 'minInclusive'))->toBe(SchemaPatterns::UNIT_ID_MIN_INCLUSIVE)
        ->and(facet($xpath, 'IdEvidJednotkyType', 'maxInclusive'))->toBe(SchemaPatterns::UNIT_ID_MAX_INCLUSIVE);
});

function facet(DOMXPath $xpath, string $type, string $facet): string
{
    $node = $xpath->query('//xs:simpleType[@name="'.$type.'"]/xs:restriction/xs:'.$facet)->item(0);

    expect($node)->toBeInstanceOf(DOMElement::class);

    if (! $node instanceof DOMElement) {
        throw new RuntimeException('Missing XSD facet '.$type.' '.$facet.'.');
    }

    return $node->getAttribute('value');
}
