<?php

declare(strict_types=1);

namespace Xul\ProjectMap;

use Illuminate\Support\ServiceProvider;
use Xul\ProjectMap\Commands\ProjectMapCommand;
use Xul\ProjectMap\Support\TreeBuilder;

class ProjectMapServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/project-map.php',
            'project-map'
        );

        $this->app->singleton(TreeBuilder::class, function (): TreeBuilder {
            return new TreeBuilder();
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/project-map.php' => config_path('project-map.php'),
        ], 'project-map-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProjectMapCommand::class,
            ]);
        }
    }
}