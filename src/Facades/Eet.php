<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Facades;

use Illuminate\Support\Facades\Facade;
use SloukaPetr\Eet\Certificate;
use SloukaPetr\Eet\Data\EetResponse;
use SloukaPetr\Eet\Data\Receipt;
use SloukaPetr\Eet\Data\SendOptions;
use SloukaPetr\Eet\EetClient;
use SloukaPetr\Eet\EetFake;

/**
 * @method static EetResponse send(Receipt $receipt, Certificate $certificate, SendOptions $options)
 * @method static EetResponse verify(Receipt $receipt, Certificate $certificate, SendOptions $options)
 */
final class Eet extends Facade
{
    public static function fake(): EetFake
    {
        $fake = new EetFake;
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return EetClient::class;
    }
}
