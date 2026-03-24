<?php

declare(strict_types=1);

namespace Xul\ProjectMap\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Xul\ProjectMap\ProjectMapServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ProjectMapServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('project-map.default_path', base_path());
        $app['config']->set('project-map.default_depth', 5);
        $app['config']->set('project-map.include_files', false);
        $app['config']->set('project-map.include_hidden', false);
        $app['config']->set('project-map.include_vendor', false);
        $app['config']->set('project-map.include_node_modules', false);
        $app['config']->set('project-map.exclude', [
            'vendor',
            'node_modules',
            '.git',
            'storage/logs',
            'bootstrap/cache',
        ]);
        $app['config']->set('project-map.exclude_names', [
            '.DS_Store',
            'Thumbs.db',
        ]);
        $app['config']->set('project-map.sort_directories_first', true);
        $app['config']->set('project-map.case_sensitive_sort', false);
        $app['config']->set('project-map.follow_symlinks', false);
        $app['config']->set('project-map.output.default_format', 'text');
        $app['config']->set('project-map.output.default_save_path', null);
    }
}