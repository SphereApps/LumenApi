<?php

namespace Sphere\Api;

use Illuminate\Support\ServiceProvider;

class LumenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->configure('api');

        $this->app->routeMiddleware([
            'api-scope' => Middleware\ApiScopeMiddleware::class,
        ]);

        // $this->app->alias(Router::class, 'api');
        $this->app->singleton(Router::class, function ($app) {
            return new Router($app->router);
        });
    }
}
