<?php

namespace SocialPreviews;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class SocialPreviewsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'social-previews');
    }

    public function boot(Repository $config, Router $router)
    {
        $this->registerRoutes($config, $router);

        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('social-previews.php'),
        ], 'social-previews');
    }

    private function registerRoutes(Repository $config, Router $router)
    {
        $url = $config->get('social-previews.route_prefix') . '/{data}.png';
        $middleware = $config->get('social-previews.route_middleware', []);

        $router
            ->get($url, RequestHandler::class)
            ->where('data', '.*')
            ->middleware($middleware)
            ->name('socialPreviews.show');
    }
}
