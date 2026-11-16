<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SloukaPetr\Eet\Support\CaeetJwt;

it('signs a renewal token with RS256 and a five minute lifetime', function () {
    $certificate = playgroundCertificate();
    $issuedAt = time();
    $token = CaeetJwt::issue($certificate, $issuedAt);
    [$encodedHeader, $encodedPayload] = explode('.', $token);

    $header = json_decode(JWT::urlsafeB64Decode($encodedHeader), true);
    $payload = json_decode(JWT::urlsafeB64Decode($encodedPayload), true);
    $publicKey = openssl_pkey_get_details(openssl_pkey_get_private($certificate->privateKey()));

    expect($header['alg'])->toBe('RS256')
        ->and($header['typ'])->toBe('JWT')
        ->and($header['x5t#S256'])->toBe($certificate->thumbprintSha256())
        ->and($payload['iat'])->toBe($issuedAt)
        ->and($payload['exp'] - $payload['iat'])->toBe(240)
        ->and($payload['exp'] - $payload['iat'])->toBeLessThanOrEqual(300);

    JWT::decode($token, new Key($publicKey['key'], 'RS256'));
});
