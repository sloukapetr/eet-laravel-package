<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SloukaPetr\Eet\Data\IssuedCertificate;
use SloukaPetr\Eet\Data\RenewalStatus;
use SloukaPetr\Eet\Exceptions\CaeetException;
use SloukaPetr\Eet\Facades\Caeet;

const REQ_ID = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

beforeEach(function () {
    config(['eet.caeet.base_url' => 'https://ca.test.caeet.gov.cz/api']);
    Http::preventStrayRequests();
});

it('submits a renewal and returns the request id', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/renew' => Http::response(['reqId' => REQ_ID], 201),
    ]);

    $reqId = Caeet::using(playgroundCertificate())->renew();

    Http::assertSent(function ($request) {
        $authorization = $request->header('Authorization')[0] ?? '';
        $parts = explode('.', substr($authorization, 7));
        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
        $payload = json_decode(JWT::urlsafeB64Decode($parts[1]), true);

        return $request->method() === 'post'
            && $request->url() === 'https://ca.test.caeet.gov.cz/api/request/renew'
            && str_starts_with($authorization, 'Bearer ')
            && $header['alg'] === 'RS256'
            && $header['x5t#S256'] === playgroundCertificate()->thumbprintSha256()
            && ($payload['exp'] - $payload['iat']) <= 300;
    });

    expect($reqId)->toBe(REQ_ID);
});

it('reads an in-process status and keeps the body poll interval', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/status' => Http::response([
            'reqId' => REQ_ID,
            'state' => 'INPROCESS',
            'pollAfterSeconds' => 2,
        ], 200, ['Retry-After' => '9']),
    ]);

    $status = Caeet::using(playgroundCertificate())->status(REQ_ID);

    expect($status->state)->toBe('INPROCESS')
        ->and($status->pollAfterSeconds)->toBe(2)
        ->and($status->rejectedReason)->toBeNull();
});

it('uses Retry-After when the status body has no poll interval', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/status' => Http::response([
            'reqId' => REQ_ID,
            'state' => 'INPROCESS',
        ], 200, ['Retry-After' => '2']),
    ]);

    $status = Caeet::using(playgroundCertificate())->status(REQ_ID);

    expect($status->pollAfterSeconds)->toBe(2);
});

it('returns the rejection reason', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/status' => Http::response([
            'reqId' => REQ_ID,
            'state' => 'REJECTED',
            'rejectedReason' => 'Selhání kontroly parametrů žádosti',
        ], 200),
    ]);

    $status = Caeet::using(playgroundCertificate())->status(REQ_ID);

    expect($status->state)->toBe('REJECTED')
        ->and($status->rejectedReason)->toBe('Selhání kontroly parametrů žádosti');
});

it('returns the issued PKCS#12 without putting the password in a dump', function () {
    $password = 'ABcd1234';

    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/claim-download' => Http::response([
            'pkcs12' => base64_encode('pkcs12-bytes'),
            'password' => $password,
            'certificate' => [
                'sn' => '24961511666821687692',
                'validFrom' => '2026-01-04 11:13:33+00:00',
                'validTo' => '2027-01-04 11:13:33+00:00',
                'state' => 'VALID',
            ],
        ], 200),
    ]);

    $issued = Caeet::using(playgroundCertificate())->claimDownload(REQ_ID);

    ob_start();
    var_dump($issued);
    $dump = ob_get_clean();

    expect($issued->pkcs12)->toBe('pkcs12-bytes')
        ->and($issued->password)->toBe($password)
        ->and($issued->serialNumber)->toBe('24961511666821687692')
        ->and($issued->state)->toBe('VALID')
        ->and($dump)->not->toContain($password)
        ->and($dump)->not->toContain('pkcs12-bytes');
});

it('confirms a download with 204', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/ack-download' => Http::response('', 204),
    ]);

    Caeet::using(playgroundCertificate())->ackDownload(REQ_ID);

    Http::assertSent(fn ($request) => $request->method() === 'post'
        && $request->url() === 'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/ack-download');
});

it('lists unfinished requests', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/not-finished' => Http::response([
            'count' => 1,
            'data' => [REQ_ID],
        ], 200),
    ]);

    expect(Caeet::using(playgroundCertificate())->notFinished())->toBe([REQ_ID]);
});

it('returns the API message for 401 and hides a PKCS#12 body', function () {
    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/renew' => Http::response([
            'code' => 2,
            'message' => 'Unauthorized access.',
        ], 401),
    ]);

    expect(fn () => Caeet::using(playgroundCertificate())->renew())
        ->toThrow(CaeetException::class, 'Unauthorized access.');

    $password = 'ABcd1234';

    Http::fake([
        'https://ca.test.caeet.gov.cz/api/request/'.REQ_ID.'/claim-download' => Http::response([
            'pkcs12' => base64_encode('secret-store'),
            'password' => $password,
            'message' => 'password '.$password,
        ], 409),
    ]);

    try {
        Caeet::using(playgroundCertificate())->claimDownload(REQ_ID);
        test()->fail('Expected a CAEET error.');
    } catch (CaeetException $exception) {
        expect($exception->getMessage())->toBe('CAEET request failed.')
            ->and($exception->getMessage())->not->toContain($password)
            ->and($exception->getMessage())->not->toContain('secret-store');
    }
});

it('hides the transport error when CAEET does not respond', function () {
    Http::fake(function () {
        throw new ConnectionException('timed out with bearer secret');
    });

    try {
        Caeet::using(playgroundCertificate())->renew();
        test()->fail('Expected a CAEET error.');
    } catch (CaeetException $exception) {
        expect($exception->getMessage())->toBe('CAEET request failed.')
            ->and($exception->getMessage())->not->toContain('bearer secret');
    }
});

it('does not call CAEET when the base URL or request id is missing', function () {
    config(['eet.caeet.base_url' => null]);

    expect(fn () => Caeet::using(playgroundCertificate())->renew())
        ->toThrow(CaeetException::class, 'CAEET base URL is not configured.');

    config(['eet.caeet.base_url' => 'https://ca.test.caeet.gov.cz/api']);

    expect(fn () => Caeet::using(playgroundCertificate())->status('../renew'))
        ->toThrow(CaeetException::class, 'CAEET request id is not a UUID.');

    Http::assertNothingSent();
});

it('replays a queued renewal without HTTP', function () {
    Http::fake();

    $fake = Caeet::fake();
    $fake->queueRenew(REQ_ID);
    $fake->queueStatus(new RenewalStatus(REQ_ID, 'ISSUED'));
    $fake->queueDownload(new IssuedCertificate('pkcs12-bytes', 'ABcd1234', '1', 'VALID'));
    $fake->queueNotFinished([REQ_ID]);

    $client = Caeet::using(playgroundCertificate());

    expect($client->renew())->toBe(REQ_ID)
        ->and($client->status(REQ_ID)->state)->toBe('ISSUED');

    $client->ackDownload(REQ_ID);

    expect($client->claimDownload(REQ_ID)->serialNumber)->toBe('1')
        ->and($client->notFinished())->toBe([REQ_ID]);

    $fake->assertSent('renew');
    $fake->assertSent('ackDownload', REQ_ID);
    Http::assertNothingSent();
});
