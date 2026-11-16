<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Illuminate\Http\Client\Factory;

final class CaeetManager
{
    public function __construct(private readonly Factory $http) {}

    public function using(Certificate $certificate): CaeetClient
    {
        $baseUrl = config('eet.caeet.base_url');

        return new CaeetClient(
            http: $this->http,
            certificate: $certificate,
            baseUrl: rtrim(is_string($baseUrl) ? $baseUrl : '', '/'),
            timeout: max(2, (int) config('eet.caeet.timeout')),
        );
    }
}
