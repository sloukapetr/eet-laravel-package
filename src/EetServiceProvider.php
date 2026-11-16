<?php

declare(strict_types=1);

namespace SloukaPetr\Eet;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class EetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/eet.php', 'eet');

        $this->app->singleton(EetClient::class, function ($app): EetClient {
            $environment = (string) config('eet.environment');
            $verify = (bool) config('eet.verify_response_signature');

            return new EetClient(
                http: $app->make(Factory::class),
                messages: new MessageBuilder,
                signer: new Signer,
                parser: new ResponseParser,
                verifier: new ResponseVerifier,
                environment: $environment,
                endpoints: (array) config('eet.endpoints'),
                timeout: max(2, (int) config('eet.timeout')),
                verifyResponseSignature: $verify || $environment === 'production',
            );
        });

        $this->app->alias(EetClient::class, 'eet');
        $this->app->singleton(CaeetManager::class);
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
