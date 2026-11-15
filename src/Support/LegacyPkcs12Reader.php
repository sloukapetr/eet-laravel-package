<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Support;

use SloukaPetr\Eet\Exceptions\InvalidCertificate;
use SloukaPetr\Eet\Exceptions\InvalidCertificatePassword;
use Symfony\Component\Process\Process;

/**
 * Converts a PKCS#12 that OpenSSL 3 cannot read (3DES) into a PEM bundle.
 * The password is passed on stdin. The encrypted input is a 0600 temp file
 * and is removed before this method returns.
 *
 * @internal
 */
final class LegacyPkcs12Reader
{
    public static function read(string $binary, string $password, string $opensslBinary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eet');

        if ($path === false) {
            throw new InvalidCertificate('Certificate could not be read.');
        }

        try {
            if (file_put_contents($path, $binary) === false) {
                throw new InvalidCertificate('Certificate could not be read.');
            }

            chmod($path, 0600);

            $process = new Process([
                $opensslBinary,
                'pkcs12',
                '-legacy',
                '-in',
                $path,
                '-passin',
                'stdin',
                '-nodes',
            ]);
            $process->setInput($password."\n");
            $process->setTimeout(15);

            try {
                $process->run();
            } catch (\Throwable) {
                throw new InvalidCertificate('Certificate could not be read.');
            }

            $stderr = $process->getErrorOutput();

            if (! $process->isSuccessful()) {
                if (self::isPasswordFailure($stderr)) {
                    throw new InvalidCertificatePassword('PKCS#12 password was rejected.');
                }

                throw new InvalidCertificate('Certificate could not be read.');
            }

            $pem = $process->getOutput();

            if (! str_contains($pem, 'BEGIN CERTIFICATE') || ! str_contains($pem, 'PRIVATE KEY')) {
                throw new InvalidCertificate('Certificate could not be read.');
            }

            return $pem;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private static function isPasswordFailure(string $stderr): bool
    {
        $haystack = strtolower($stderr);

        return str_contains($haystack, 'invalid password')
            || str_contains($haystack, 'mac verify');
    }
}
