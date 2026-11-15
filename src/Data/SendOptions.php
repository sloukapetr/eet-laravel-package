<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Data;

use SloukaPetr\Eet\Exceptions\ValidationException;
use SloukaPetr\Eet\Support\SchemaPatterns;

final readonly class SendOptions
{
    private const UUID_VERSION_4 = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public function __construct(
        public bool $firstSend = true,
        public bool $verification = false,
        public ?string $uuid = null,
        public ?int $timeout = null,
    ) {
        if ($this->uuid !== null) {
            $isSchemaUuid = SchemaPatterns::matches('UUIDType', $this->uuid);
            $isVersion4 = preg_match('~^'.self::UUID_VERSION_4.'$~', $this->uuid) === 1;

            if (! $isSchemaUuid || ! $isVersion4) {
                throw new ValidationException('uuid', 'uuid_zpravy must be a UUID version 4.');
            }
        }

        if ($this->timeout !== null && $this->timeout < 2) {
            throw new ValidationException('timeout', 'Timeout must be at least 2 seconds.');
        }
    }
}
