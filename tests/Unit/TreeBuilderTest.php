<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Xul\ProjectMap\Support\TreeBuilder;

beforeEach(function (): void {
    $this->testPath = base_path('tests-temp-tree-builder');

    if (File::exists($this->testPath)) {
        File::deleteDirectory($this->testPath);
    }

    File::ensureDirectoryExists($this->testPath . '/app/Services');
    File::ensureDirectoryExists($this->testPath . '/routes');
    File::ensureDirectoryExists($this->testPath . '/vendor/package');
    File::ensureDirectoryExists($this->testPath . '/node_modules/library');
    File::ensureDirectoryExists($this->testPath . '/.git');

    File::put($this->testPath . '/index.php', '<?php echo "Hello";');
    File::put($this->testPath . '/.env', 'APP_NAME=Test');
    File::put($this->testPath . '/app/Services/TestService.php', '<?php');
    File::put($this->testPath . '/routes/web.php', '<?php');
});

afterEach(function (): void {
    if (File::exists($this->testPath)) {
        File::deleteDirectory($this->testPath);
    }
});

it('builds a directory tree', function (): void {
    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        false
    );

    $names = array_column($tree, 'name');

    expect($names)->toContain('app', 'routes')
        ->not->toContain('index.php');
});

it('can include files', function (): void {
    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        true
    );

    $names = array_column($tree, 'name');

    expect($names)->toContain('index.php');
});

it('excludes hidden entries by default', function (): void {
    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        true
    );

    $names = array_column($tree, 'name');

    expect($names)->not->toContain('.env', '.git');
});

it('can include hidden entries', function (): void {
    config()->set('project-map.include_hidden', true);

    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        true
    );

    $names = array_column($tree, 'name');

    expect($names)->toContain('.env');
});

it('excludes vendor by default', function (): void {
    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        false
    );

    $names = array_column($tree, 'name');

    expect($names)->not->toContain('vendor');
});

it('can include vendor when enabled', function (): void {
    config()->set('project-map.include_vendor', true);

    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        false
    );

    $names = array_column($tree, 'name');

    expect($names)->toContain('vendor');
});

it('can include node modules when enabled', function (): void {
    config()->set('project-map.include_node_modules', true);

    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        false
    );

    $names = array_column($tree, 'name');

    expect($names)->toContain('node_modules');
});

it('respects the maximum depth', function (): void {
    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        1,
        false
    );

    $appNode = collect($tree)->firstWhere('name', 'app');

    expect($appNode)->toBeArray()
        ->not->toHaveKey('children');
});

it('can render text output', function (): void {
    $builder = new TreeBuilder();

    $tree = $builder->build(
        $this->testPath,
        config('project-map.exclude', []),
        5,
        true
    );

    $output = $builder->renderText($tree, 'tests-temp-tree-builder');

    expect($output)
        ->toContain('tests-temp-tree-builder/')
        ->toContain('app/');
});