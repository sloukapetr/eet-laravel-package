<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Illuminate\Support\ServiceProvider;

final class EetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/eet.php', 'eet');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__).'/config/eet.php' => config_path('eet.php'),
            ], 'eet-config');
        }
    }
}
