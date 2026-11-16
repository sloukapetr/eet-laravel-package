<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Facades;

use Illuminate\Support\Facades\Facade;
use SloukaPetr\Eet\CaeetClient;
use SloukaPetr\Eet\CaeetFake;
use SloukaPetr\Eet\Certificate;

/**
 * @method static CaeetClient using(Certificate $certificate)
 */
final class Caeet extends Facade
{
    public static function fake(): CaeetFake
    {
        $fake = new CaeetFake;
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return \SloukaPetr\Eet\CaeetManager::class;
    }
}
