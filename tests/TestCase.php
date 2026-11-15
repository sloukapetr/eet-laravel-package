<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SloukaPetr\Eet\EetServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EetServiceProvider::class,
        ];
    }
}
