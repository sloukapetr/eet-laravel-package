<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Data;

final readonly class IssuedCertificate
{
    public function __construct(
        public string $pkcs12,
        public string $password,
        public string $serialNumber,
        public string $state,
    ) {}

    /**
     * @return array{serialNumber: string, state: string}
     */
    public function __debugInfo(): array
    {
        return [
            'serialNumber' => $this->serialNumber,
            'state' => $this->state,
        ];
    }
}
