<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Exceptions;

use InvalidArgumentException;

final class ValidationException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        public readonly string $reason,
    ) {
        parent::__construct($field.': '.$reason);
    }
}
