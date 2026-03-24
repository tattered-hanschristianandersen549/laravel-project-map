<?php

declare(strict_types=1);

namespace Xul\ProjectMap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Xul\ProjectMap\Support\TreeBuilder;

class ProjectMapCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'project:map
                            {path? : The base path to scan}
                            {--depth= : The maximum scan depth}
                            {--exclude= : A comma-separated list of paths to exclude}
                            {--files : Include files in the generated output}
                            {--hidden : Include hidden files and directories}
                            {--vendor : Include the vendor directory}
                            {--node-modules : Include the node_modules directory}
                            {--json : Output the result as JSON}
                            {--format= : Output format (text or json)}
                            {--compact : Output a compact result without extra console messaging}
                            {--save= : Save the generated output to a file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a map of the project directory structure';

    /**
     * Execute the console command.
     *
     * @param  \Xul\ProjectMap\Support\TreeBuilder  $builder
     * @return int
     */
    public function handle(TreeBuilder $builder): int
    {
        try {
            $this->applyRuntimeConfigurationOverrides();

            $basePath = $this->resolveBasePath();

            if (! File::isDirectory($basePath)) {
                $this->error(sprintf(
                    'The provided path [%s] is not a valid directory.',
                    $basePath
                ));

                return self::FAILURE;
            }

            $depth = $this->resolveDepth();
            $includeFiles = $this->resolveIncludeFiles();
            $outputFormat = $this->resolveOutputFormat();
            $excludedPaths = $this->resolveExcludedPaths();

            $tree = $builder->build(
                $basePath,
                $excludedPaths,
                $depth,
                $includeFiles
            );

            $output = $outputFormat === 'json'
                ? $this->renderJsonOutput($tree)
                : $builder->renderText($tree, basename($basePath), $this->isCompactMode());

            $this->writeOutput($output);

            $savePath = $this->resolveSavePath();

            if ($savePath !== null) {
                $this->saveOutputToFile($output, $savePath);
            }

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Resolve the base path to scan.
     *
     * @return string
     */
    protected function resolveBasePath(): string
    {
        $path = $this->argument('path');

        if (! is_string($path) || trim($path) === '') {
            /** @var string $defaultPath */
            $defaultPath = config('project-map.default_path', base_path());

            return $this->normalizePath($defaultPath);
        }

        if ($this->isAbsolutePath($path)) {
            return $this->normalizePath($path);
        }

        return $this->normalizePath(base_path($path));
    }

    /**
     * Resolve the maximum depth for the scan.
     *
     * @return int
     */
    protected function resolveDepth(): int
    {
        $depth = $this->option('depth');

        if ($depth === null || $depth === '') {
            return max(0, (int) config('project-map.default_depth', 5));
        }

        return max(0, (int) $depth);
    }

    /**
     * Resolve whether files should be included in the output.
     *
     * @return bool
     */
    protected function resolveIncludeFiles(): bool
    {
        if ((bool) $this->option('files')) {
            return true;
        }

        return (bool) config('project-map.include_files', false);
    }

    /**
     * Resolve the requested output format.
     *
     * @return string
     */
    protected function resolveOutputFormat(): string
    {
        if ((bool) $this->option('json')) {
            return 'json';
        }

        $format = $this->option('format');

        if (! is_string($format) || trim($format) === '') {
            /** @var string $configuredFormat */
            $configuredFormat = config('project-map.output.default_format', 'text');

            $format = $configuredFormat;
        }

        $normalizedFormat = strtolower(trim($format));

        if (! in_array($normalizedFormat, ['text', 'json'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported output format [%s]. Supported formats are [text, json].',
                $format
            ));
        }

        return $normalizedFormat;
    }

    /**
     * Resolve the excluded paths for the scan.
     *
     * Command-line exclusions override configured exclusions when provided.
     *
     * @return array<int, string>
     */
    protected function resolveExcludedPaths(): array
    {
        $exclude = $this->option('exclude');

        if (! is_string($exclude) || trim($exclude) === '') {
            /** @var array<int, string> $configuredExcludedPaths */
            $configuredExcludedPaths = config('project-map.exclude', []);

            return $configuredExcludedPaths;
        }

        return collect(explode(',', $exclude))
            ->map(static fn (string $path): string => trim($path))
            ->filter(static fn (string $path): bool => $path !== '')
            ->values()
            ->all();
    }

    /**
     * Apply runtime configuration overrides for the current command execution.
     *
     * @return void
     */
    protected function applyRuntimeConfigurationOverrides(): void
    {
        if ((bool) $this->option('hidden')) {
            config()->set('project-map.include_hidden', true);
        }

        if ((bool) $this->option('vendor')) {
            config()->set('project-map.include_vendor', true);
        }

        if ((bool) $this->option('node-modules')) {
            config()->set('project-map.include_node_modules', true);
        }
    }

    /**
     * Render the tree as a JSON string.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return string
     */
    protected function renderJsonOutput(array $tree): string
    {
        $encoded = json_encode(
            $tree,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        return $encoded !== false ? $encoded : '[]';
    }

    /**
     * Write the generated output to the console.
     *
     * @param  string  $output
     * @return void
     */
    protected function writeOutput(string $output): void
    {
        $this->line($output);
    }

    /**
     * Resolve the target file path for saving output.
     *
     * @return string|null
     */
    protected function resolveSavePath(): ?string
    {
        $savePath = $this->option('save');

        if (is_string($savePath) && trim($savePath) !== '') {
            return $this->normalizeSavePath($savePath);
        }

        /** @var string|null $configuredSavePath */
        $configuredSavePath = config('project-map.output.default_save_path');

        if (is_string($configuredSavePath) && trim($configuredSavePath) !== '') {
            return $this->normalizeSavePath($configuredSavePath);
        }

        return null;
    }

    /**
     * Save the generated output to a file.
     *
     * @param  string  $output
     * @param  string  $path
     * @return void
     */
    protected function saveOutputToFile(string $output, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $output . PHP_EOL);

        if (! $this->isCompactMode()) {
            $this->info(sprintf('Project map saved to [%s].', $path));
        }
    }

    /**
     * Normalize a save path into an absolute path.
     *
     * @param  string  $path
     * @return string
     */
    protected function normalizeSavePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $this->normalizePath($path);
        }

        return $this->normalizePath(base_path($path));
    }

    /**
     * Determine whether compact mode is enabled.
     *
     * @return bool
     */
    protected function isCompactMode(): bool
    {
        return (bool) $this->option('compact');
    }

    /**
     * Normalize a filesystem path.
     *
     * @param  string  $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        $resolvedPath = realpath($path) ?: $path;
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedPath);

        return rtrim($normalizedPath, DIRECTORY_SEPARATOR);
    }

    /**
     * Determine whether the given path is absolute.
     *
     * @param  string  $path
     * @return bool
     */
    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }
}