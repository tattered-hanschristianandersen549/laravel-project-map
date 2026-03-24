<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->testPath = base_path('tests-temp-project-map-command');

    if (File::exists($this->testPath)) {
        File::deleteDirectory($this->testPath);
    }

    File::ensureDirectoryExists($this->testPath . '/app');
    File::ensureDirectoryExists($this->testPath . '/routes');
    File::ensureDirectoryExists($this->testPath . '/vendor/package');
    File::ensureDirectoryExists($this->testPath . '/node_modules/library');

    File::put($this->testPath . '/index.php', '<?php');
    File::put($this->testPath . '/routes/web.php', '<?php');
    File::put($this->testPath . '/.env', 'APP_NAME=Test');
});

afterEach(function (): void {
    if (File::exists($this->testPath)) {
        File::deleteDirectory($this->testPath);
    }
});

it('runs successfully', function (): void {
    $this->artisan('project:map', ['path' => $this->testPath])
        ->expectsOutputToContain('tests-temp-project-map-command/')
        ->assertExitCode(0);
});

it('can include files', function (): void {
    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--files' => true,
    ])
        ->expectsOutputToContain('index.php')
        ->assertExitCode(0);
});

it('can output json', function (): void {
    $savePath = $this->testPath . '/output/project-map.json';

    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--format' => 'json',
        '--files' => true,
        '--save' => $savePath,
        '--compact' => true,
    ])->assertExitCode(0);

    expect(File::exists($savePath))->toBeTrue();

    $contents = File::get($savePath);

    expect($contents)->toBeJson();

    $decoded = json_decode($contents, true);

    expect($decoded)->toBeArray();

    $appNode = collect($decoded)->firstWhere('name', 'app');

    expect($appNode)->toBeArray()
        ->and($appNode['type'])->toBe('directory');
});

it('can save output to a file', function (): void {
    $savePath = $this->testPath . '/output/project-map.txt';

    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--save' => $savePath,
    ])->assertExitCode(0);

    expect(File::exists($savePath))->toBeTrue();

    $contents = File::get($savePath);

    expect($contents)->toContain('tests-temp-project-map-command/');
});

it('can include vendor when requested', function (): void {
    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--vendor' => true,
    ])
        ->expectsOutputToContain('vendor/')
        ->assertExitCode(0);
});

it('can include node modules when requested', function (): void {
    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--node-modules' => true,
    ])
        ->expectsOutputToContain('node_modules/')
        ->assertExitCode(0);
});

it('can include hidden entries when requested', function (): void {
    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--files' => true,
        '--hidden' => true,
    ])
        ->expectsOutputToContain('.env')
        ->assertExitCode(0);
});

it('rejects an invalid output format', function (): void {
    $this->artisan('project:map', [
        'path' => $this->testPath,
        '--format' => 'xml',
    ])
        ->expectsOutputToContain('Unsupported output format [xml].')
        ->assertExitCode(1);
});