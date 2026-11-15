<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SloukaPetr\Eet\Certificate;
use SloukaPetr\Eet\Exceptions\CertificateExpired;
use SloukaPetr\Eet\Exceptions\InvalidCertificate;
use SloukaPetr\Eet\Exceptions\InvalidCertificatePassword;
use SloukaPetr\Eet\Support\LegacyPkcs12Reader;
use Symfony\Component\Process\Process;

it('reads a 3DES playground certificate through the legacy conversion', function (string $file, string $eic) {
    $binary = playgroundPkcs12($file);
    $password = playgroundPassword();
    $bags = [];

    expect(openssl_pkcs12_read($binary, $bags, $password))->toBeFalse();

    $pem = LegacyPkcs12Reader::read($binary, $password, (string) config('eet.openssl_binary'));
    $certificate = Certificate::fromPkcs12($binary, $password);

    expect(str_contains($pem, 'BEGIN CERTIFICATE'))->toBeTrue()
        ->and(str_contains($pem, 'PRIVATE KEY'))->toBeTrue()
        ->and($certificate->eic())->toBe($eic)
        ->and($certificate->isValidAt(CarbonImmutable::parse('2026-11-15T12:00:00Z')))->toBeTrue();
})->with([
    'eight digits' => ['CA_EET-Playground-CZ00000019.p12', 'CZ00000019'],
    'nine digits' => ['CA_EET-Playground-CZ683555118.p12', 'CZ683555118'],
    'ten digits' => ['CA_EET-Playground-CZ8551015704.p12', 'CZ8551015704'],
]);

it('exposes the playground certificate identity and sha256 thumbprint', function () {
    $certificate = Certificate::fromPkcs12(playgroundPkcs12('CA_EET-Playground-CZ00000019.p12'), playgroundPassword());

    expect($certificate->serialNumber())->toBe('15584C0A86BB6E65CC5C7097792CB26481A9A6AC')
        ->and($certificate->notBefore()->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-05-13T09:40:23Z')
        ->and($certificate->notAfter()->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2027-05-14T09:40:23Z')
        ->and(($certificate->notAfter()->getTimestamp() - $certificate->notBefore()->getTimestamp()) / 86400)->toBe(366)
        ->and($certificate->thumbprintSha256())->toBe('fsgG5h4AqdmFHLkHPbumbaCuBgSmLWLNAqk8jtwGHAU')
        ->and($certificate->isValidAt($certificate->notBefore()))->toBeTrue()
        ->and($certificate->isValidAt($certificate->notBefore()->subSecond()))->toBeFalse()
        ->and($certificate->isValidAt($certificate->notAfter()->subSecond()))->toBeTrue()
        ->and($certificate->isValidAt($certificate->notAfter()))->toBeFalse();

    $publicKey = openssl_pkey_get_details(openssl_pkey_get_public($certificate->certificatePem()));
    $privateKey = openssl_pkey_get_details(openssl_pkey_get_private($certificate->privateKey()));

    expect(is_array($publicKey) && is_array($privateKey))->toBeTrue()
        ->and(hash('sha256', (string) $publicKey['key']))->toBe(hash('sha256', (string) $privateKey['key']));
});

it('exports a modern AES pkcs12 that PHP can read without the legacy provider', function () {
    $password = playgroundPassword();
    $original = playgroundPkcs12('CA_EET-Playground-CZ00000019.p12');
    $certificate = Certificate::fromPkcs12($original, $password);
    $exported = $certificate->toPkcs12('export-pass');
    $bags = [];

    expect($exported)->not->toBe($original)
        ->and(openssl_pkcs12_read($exported, $bags, 'export-pass'))->toBeTrue();

    $again = Certificate::fromPkcs12($exported, 'export-pass');

    expect($again->eic())->toBe($certificate->eic())
        ->and($again->thumbprintSha256())->toBe($certificate->thumbprintSha256())
        ->and(pkcs12UsesAes($exported, 'export-pass'))->toBeTrue();
});

it('rejects a wrong playground password without putting it in the exception', function () {
    $password = playgroundPassword();
    $wrong = $password.'-nope';

    try {
        Certificate::fromPkcs12(playgroundPkcs12('CA_EET-Playground-CZ00000019.p12'), $wrong);
        test()->fail('Expected the password to be rejected.');
    } catch (InvalidCertificatePassword $exception) {
        expect($exception->getMessage())->not->toContain($password)
            ->and($exception->getMessage())->not->toContain($wrong)
            ->and($exception->getMessage())->not->toContain('PRIVATE KEY')
            ->and($exception->getMessage())->not->toContain('BEGIN CERTIFICATE');
    }
});

it('rejects a file that is not a pkcs12', function () {
    expect(fn () => Certificate::fromPkcs12('not-a-certificate', 'export-pass'))
        ->toThrow(InvalidCertificate::class);
});

it('refuses the private key of an expired certificate', function () {
    $password = 'export-pass';
    $binary = expiredPkcs12($password);
    $certificate = Certificate::fromPkcs12($binary, $password);

    expect($certificate->eic())->toBe('CZ00000019')
        ->and($certificate->isValidAt(CarbonImmutable::parse('2020-01-01T12:00:00Z')))->toBeTrue()
        ->and($certificate->isValidAt(CarbonImmutable::now('UTC')))->toBeFalse();

    try {
        $certificate->privateKey();
        test()->fail('Expected an expired certificate.');
    } catch (CertificateExpired $exception) {
        expect($exception->getMessage())->not->toContain($password)
            ->and($exception->getMessage())->not->toContain('PRIVATE KEY');
    }

    expect(fn () => $certificate->assertValidAt(CarbonImmutable::now('UTC')))
        ->toThrow(CertificateExpired::class);
});

function playgroundPassword(): string
{
    $password = getenv('EET_PLAYGROUND_P12_PASSWORD');

    if (! is_string($password) || $password === '') {
        throw new RuntimeException('Set EET_PLAYGROUND_P12_PASSWORD in .env.testing.');
    }

    return $password;
}

function playgroundPkcs12(string $file): string
{
    $binary = file_get_contents(dirname(__DIR__).'/fixtures/playground/'.$file);

    if ($binary === false) {
        throw new RuntimeException('Missing playground fixture '.$file.'.');
    }

    return $binary;
}

function pkcs12UsesAes(string $binary, string $password): bool
{
    $path = tempnam(sys_get_temp_dir(), 'eet');

    if ($path === false) {
        return false;
    }

    try {
        file_put_contents($path, $binary);
        chmod($path, 0600);

        $process = new Process([
            (string) config('eet.openssl_binary'),
            'pkcs12',
            '-info',
            '-in',
            $path,
            '-passin',
            'stdin',
            '-nokeys',
        ]);
        $process->setInput($password."\n");
        $process->run();

        $output = $process->getOutput().$process->getErrorOutput();

        return str_contains($output, 'AES-256-CBC')
            && ! str_contains($output, '3DES')
            && ! str_contains($output, 'RC2');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function expiredPkcs12(string $password): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'eet-expired-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $key = $directory.DIRECTORY_SEPARATOR.'key.pem';
    $certificate = $directory.DIRECTORY_SEPARATOR.'cert.pem';
    $pkcs12 = $directory.DIRECTORY_SEPARATOR.'cert.p12';

    try {
        $request = new Process([
            (string) config('eet.openssl_binary'),
            'req', '-x509', '-newkey', 'rsa:2048',
            '-keyout', $key,
            '-out', $certificate,
            '-nodes',
            '-subj', '/CN=CZ00000019',
            '-not_before', '20200101000000Z',
            '-not_after', '20200102000000Z',
        ]);
        $request->run();

        $export = new Process([
            (string) config('eet.openssl_binary'),
            'pkcs12', '-export',
            '-in', $certificate,
            '-inkey', $key,
            '-out', $pkcs12,
            '-passout', 'stdin',
            '-keypbe', 'AES-256-CBC',
            '-certpbe', 'AES-256-CBC',
        ]);
        $export->setInput($password."\n");
        $export->run();

        $binary = file_get_contents($pkcs12);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Expired certificate fixture could not be created.');
        }

        return $binary;
    } finally {
        foreach ([$key, $certificate, $pkcs12] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
