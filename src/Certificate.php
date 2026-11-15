<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Exceptions\CertificateExpired;
use SloukaPetr\Eet\Exceptions\InvalidCertificate;
use SloukaPetr\Eet\Exceptions\InvalidCertificatePassword;
use SloukaPetr\Eet\Support\LegacyPkcs12Reader;
use Throwable;

final class Certificate
{
    private function __construct(
        private readonly string $certificatePem,
        private readonly string $privateKeyPem,
        private readonly string $eic,
        private readonly string $serialNumber,
        private readonly CarbonImmutable $notBefore,
        private readonly CarbonImmutable $notAfter,
    ) {}

    public static function fromPkcs12(string $binary, string $password): self
    {
        $bags = [];

        if (openssl_pkcs12_read($binary, $bags, $password)) {
            $certificate = $bags['cert'] ?? '';
            $key = $bags['pkey'] ?? '';

            if (is_string($certificate) && $certificate !== '' && is_string($key) && $key !== '') {
                return self::fromPair($certificate, $key);
            }
        }

        $nativeErrors = self::drainOpensslErrors();

        if (self::isPasswordFailure($nativeErrors)) {
            throw new InvalidCertificatePassword('PKCS#12 password was rejected.');
        }

        return self::fromPem(LegacyPkcs12Reader::read($binary, $password, self::opensslBinary()));
    }

    public function eic(): string
    {
        return $this->eic;
    }

    public function serialNumber(): string
    {
        return $this->serialNumber;
    }

    public function notBefore(): CarbonImmutable
    {
        return $this->notBefore;
    }

    public function notAfter(): CarbonImmutable
    {
        return $this->notAfter;
    }

    /**
     * SHA-256 of the DER certificate, base64url without padding (JWT x5t#S256).
     */
    public function thumbprintSha256(): string
    {
        $body = preg_replace('/-----[^-]+-----|\s+/', '', $this->certificatePem);

        if (! is_string($body)) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        $der = base64_decode($body, true);

        if ($der === false) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        return rtrim(strtr(base64_encode(hash('sha256', $der, true)), '+/', '-_'), '=');
    }

    public function isValidAt(CarbonImmutable $instant): bool
    {
        $timestamp = $instant->getTimestamp();

        return $timestamp >= $this->notBefore->getTimestamp()
            && $timestamp < $this->notAfter->getTimestamp();
    }

    public function assertValidAt(CarbonImmutable $instant): void
    {
        if (! $this->isValidAt($instant)) {
            throw new CertificateExpired('Certificate is outside its validity period.');
        }
    }

    /**
     * @internal The signer uses this. Application code must not read or log the key.
     */
    public function privateKey(): string
    {
        $this->assertValidAt(CarbonImmutable::now('UTC'));

        return $this->privateKeyPem;
    }

    public function toPkcs12(string $password): string
    {
        $exported = '';
        $ok = openssl_pkcs12_export($this->certificatePem, $exported, $this->privateKeyPem, $password);

        self::drainOpensslErrors();

        if (! $ok || $exported === '') {
            throw new InvalidCertificate('Certificate could not be exported.');
        }

        return $exported;
    }

    /**
     * @internal The signer embeds this certificate. Application code must not log it.
     */
    public function certificatePem(): string
    {
        return $this->certificatePem;
    }

    private static function fromPem(string $pem): self
    {
        if (preg_match('/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----.*?-----END (?:RSA |EC )?PRIVATE KEY-----/s', $pem, $keyMatch) !== 1) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        if (preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $certificateMatches) < 1) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        $key = $keyMatch[0];
        $leaf = null;

        foreach ($certificateMatches[0] as $candidate) {
            if (openssl_x509_check_private_key($candidate, $key)) {
                $leaf = $candidate;
                break;
            }
        }

        self::drainOpensslErrors();

        if (! is_string($leaf)) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        return self::fromPair($leaf, $key);
    }

    private static function fromPair(string $certificatePem, string $privateKeyPem): self
    {
        $parsed = openssl_x509_parse($certificatePem);

        if ($parsed === false) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        $commonName = $parsed['subject']['CN'] ?? '';

        if (is_array($commonName)) {
            $commonName = $commonName[0] ?? '';
        }

        if (! is_string($commonName) || $commonName === '') {
            throw new InvalidCertificate('Certificate subject is missing a common name.');
        }

        $serial = strtoupper(str_replace(':', '', (string) ($parsed['serialNumberHex'] ?? '')));

        if ($serial === '') {
            throw new InvalidCertificate('Certificate serial number is missing.');
        }

        $notBefore = $parsed['validFrom_time_t'] ?? null;
        $notAfter = $parsed['validTo_time_t'] ?? null;

        if (! is_int($notBefore) || ! is_int($notAfter) || $notAfter <= $notBefore) {
            throw new InvalidCertificate('Certificate validity period is missing.');
        }

        return new self(
            certificatePem: $certificatePem,
            privateKeyPem: $privateKeyPem,
            eic: $commonName,
            serialNumber: $serial,
            notBefore: CarbonImmutable::createFromTimestampUTC($notBefore),
            notAfter: CarbonImmutable::createFromTimestampUTC($notAfter),
        );
    }

    private static function opensslBinary(): string
    {
        if (! function_exists('config')) {
            return 'openssl';
        }

        try {
            $configured = config('eet.openssl_binary');
        } catch (Throwable) {
            return 'openssl';
        }

        if (! is_string($configured) || $configured === '') {
            return 'openssl';
        }

        return $configured;
    }

    private static function drainOpensslErrors(): string
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return implode("\n", $errors);
    }

    private static function isPasswordFailure(string $errors): bool
    {
        $haystack = strtolower($errors);

        return str_contains($haystack, 'invalid password')
            || str_contains($haystack, 'mac verify');
    }
}
