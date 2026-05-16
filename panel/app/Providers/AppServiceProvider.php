<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Fruitcake\Cors\CorsService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind a CorsService that resolves callable options from config('cors')
        $this->app->singleton(CorsService::class, function ($app) {
            $options = $app['config']->get('cors', []);

            $resolver = function ($value) use (&$resolver) {
                if (is_callable($value)) {
                    return $value();
                }

                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        $value[$k] = $resolver($v);
                    }
                }

                return $value;
            };

            $resolvedOptions = $resolver($options);

            return new CorsService($resolvedOptions);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
