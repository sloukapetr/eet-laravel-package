<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Support;

/**
 * Pattern facets copied from docs/EETXMLSchema.xsd. Tests compare these values
 * with the schema file so a renamed type cannot drift silently.
 */
final class SchemaPatterns
{
    /**
     * @var array<string, string>
     */
    public const TYPES = [
        'string20' => '[0-9a-zA-Z\\.,:;/#\\-_ ]{1,20}',
        'string25' => '[0-9a-zA-Z\\.,:;/#\\-_ ]{1,25}',
        'dateTime' => '\\d{4}-\\d\\d-\\d\\dT\\d\\d:\\d\\d:\\d\\d(Z|[+\\-]\\d\\d:\\d\\d)',
        'CastkaType' => '((0|-?[1-9]\\d{0,7})\\.\\d\\d|-0\\.(0[1-9]|[1-9]\\d))',
        'IdEvidJednotkyType' => '[1-9][0-9]{0,8}',
        'UUIDType' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}',
        'CZEICType' => 'CZ[0-9]{8,10}',
        'PokType' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}-[0-9a-fA-F]{2}',
    ];

    public const AMOUNT_MIN_EXCLUSIVE = '-100000000';

    public const AMOUNT_MAX_EXCLUSIVE = '100000000';

    public const UNIT_ID_MIN_INCLUSIVE = '1';

    public const UNIT_ID_MAX_INCLUSIVE = '999999999';

    public static function matches(string $type, string $value): bool
    {
        return preg_match('~^'.self::TYPES[$type].'$~', $value) === 1;
    }
}
