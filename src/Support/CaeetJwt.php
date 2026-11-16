<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Support;

use Firebase\JWT\JWT;
use SloukaPetr\Eet\Certificate;

final class CaeetJwt
{
    public static function issue(Certificate $certificate, ?int $issuedAt = null): string
    {
        $issuedAt ??= time();

        return JWT::encode(
            [
                'iat' => $issuedAt,
                'exp' => $issuedAt + 240,
            ],
            $certificate->privateKey(),
            'RS256',
            null,
            [
                'typ' => 'JWT',
                'x5t#S256' => $certificate->thumbprintSha256(),
            ],
        );
    }
}
