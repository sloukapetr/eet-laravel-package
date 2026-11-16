# eet-laravel-package

Laravel package for the Czech EET 2.0 SOAP interface and CAEET certificate renewal. It signs and sends one receipt at a time. It does not store certificates, decide when to report a sale, or retry a failed send.

## Installation

```bash
composer require sloukapetr/eet-laravel-package
php artisan vendor:publish --tag=eet-config
```

## Environment

```env
EET_ENVIRONMENT=playground
EET_TIMEOUT=5
EET_VERIFY_RESPONSE_SIGNATURE=true
EET_OPENSSL_BINARY=openssl
EET_CAEET_BASE_URL=
EET_CAEET_TIMEOUT=10
```

`EET_ENVIRONMENT` is `playground` or `production`. Playground receipts are not valid production confirmations. `EET_CAEET_BASE_URL` stays empty until you choose one of the CAEET servers from `docs/caeetapi/caeetapi_jwt.yml`:

- test `https://ca.test.caeet.gov.cz/api`
- trial `https://ca.zkus.caeet.gov.cz/api`
- production `https://ca.caeet.gov.cz/api`

## Send a receipt

Amounts are integer minor units. The package formats them for the message.

```php
use SloukaPetr\Eet\Certificate;
use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\Facades\Eet;

$certificate = Certificate::fromPkcs12($pkcs12, $password);
$receipt = new Receipt(
    eic: 'CZ00000019',
    unitId: 1,
    deviceId: 'pokladna',
    serial: '2026-000001',
    soldAt: Carbon\CarbonImmutable::now('UTC'),
    totalMinor: 10000,
);

$response = Eet::send($receipt, $certificate, new SendOptions(firstSend: true));
$check = Eet::verify($receipt, $certificate, new SendOptions);
```

A DNS, TLS, timeout or HTTP 5xx failure returns `EetResponse` with `isTemporaryError()`. Invalid receipt data throws `ValidationException` before any request. Do not log `requestXml` or `responseXml`: they contain the certificate.

## Renewal

```php
use SloukaPetr\Eet\Facades\Caeet;

$client = Caeet::using($certificate);
$reqId = $client->renew();
$status = $client->status($reqId);
$issued = $client->claimDownload($reqId);
$client->ackDownload($reqId);
```

`claimDownload()` returns the PKCS#12 bytes and password. Do not log that object.

## Tests

```php
$fake = Eet::fake();
$fake->ok('03965780-6457-4842-bd80-5f9195c0b8c8-ff', test: true);

$caeet = Caeet::fake();
$caeet->queueRenew('3fa85f64-5717-4562-b3fc-2c963f66afa6');
```

The playground integration test runs only with `EET_PLAYGROUND_TEST=1`.
