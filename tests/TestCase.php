<?php

declare(strict_types=1);

namespace SloukaPetr\Eet\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SloukaPetr\Eet\EetServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        $this->loadPackageTestingEnv();

        parent::setUp();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EetServiceProvider::class,
        ];
    }

    private function loadPackageTestingEnv(): void
    {
        $path = dirname(__DIR__).'/.env.testing';

        if (! is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $value = trim($parts[1], " \t\"'");
            putenv($parts[0].'='.$value);
            $_ENV[$parts[0]] = $value;
            $_SERVER[$parts[0]] = $value;
        }
    }
}
