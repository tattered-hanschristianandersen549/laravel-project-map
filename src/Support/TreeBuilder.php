<?php

declare(strict_types=1);

namespace Xul\ProjectMap\Support;

use Illuminate\Support\Collection;
use SplFileInfo;

class TreeBuilder
{
    /**
     * Build a project tree structure from the given path.
     *
     * @param  string  $path  The base path to scan.
     * @param  array<int, string>  $excludedPaths  Explicit paths to exclude.
     * @param  int  $maxDepth  The maximum traversal depth.
     * @param  bool  $includeFiles  Whether files should be included in the output.
     * @return array<int, array<string, mixed>>
     */
    public function build(
        string $path,
        array $excludedPaths = [],
        int $maxDepth = 5,
        bool $includeFiles = false
    ): array {
        $resolvedPath = $this->normalizePath($path);

        $effectiveExcludedPaths = $this->resolveExcludedPaths(
            $resolvedPath,
            $excludedPaths
        );

        return $this->buildTree(
            $resolvedPath,
            $effectiveExcludedPaths,
            1,
            $maxDepth,
            $includeFiles
        );
    }

    /**
     * Render the tree structure as plain text.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @param  string  $rootName  The root label to display.
     * @param  bool  $compact  Whether compact rendering should be used.
     * @return string
     */
    public function renderText(array $tree, string $rootName, bool $compact = false): string
    {
        $lines = [rtrim($rootName, DIRECTORY_SEPARATOR) . '/'];

        $this->renderTreeLines($tree, $lines, '', $compact);

        return implode(PHP_EOL, $lines);
    }

    /**
     * Recursively build the tree structure for the given directory.
     *
     * @param  string  $path
     * @param  array<int, string>  $excludedPaths
     * @param  int  $currentDepth
     * @param  int  $maxDepth
     * @param  bool  $includeFiles
     * @return array<int, array<string, mixed>>
     */
    protected function buildTree(
        string $path,
        array $excludedPaths,
        int $currentDepth,
        int $maxDepth,
        bool $includeFiles
    ): array {
        if (! is_dir($path) || $currentDepth > $maxDepth) {
            return [];
        }

        $entries = $this->getEntries($path, $includeFiles)
            ->filter(function (SplFileInfo $entry) use ($excludedPaths): bool {
                return ! $this->shouldExcludeEntry($entry, $excludedPaths);
            })
            ->values();

        $tree = [];

        foreach ($entries as $entry) {
            $node = $this->makeNode($entry);

            if ($entry->isDir() && $currentDepth < $maxDepth) {
                $children = $this->buildTree(
                    $entry->getPathname(),
                    $excludedPaths,
                    $currentDepth + 1,
                    $maxDepth,
                    $includeFiles
                );

                if ($children !== []) {
                    $node['children'] = $children;
                }
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Create a tree node for the given filesystem entry.
     *
     * @param  SplFileInfo  $entry
     * @return array<string, mixed>
     */
    protected function makeNode(SplFileInfo $entry): array
    {
        return [
            'name' => $entry->getFilename(),
            'type' => $entry->isDir() ? 'directory' : 'file',
            'path' => $this->normalizePath($entry->getPathname()),
        ];
    }

    /**
     * Retrieve the entries for a directory, applying package-level filtering
     * and sorting rules.
     *
     * This implementation uses scandir so that hidden files and directories
     * remain discoverable when package configuration allows them.
     *
     * @param  string  $path
     * @param  bool  $includeFiles
     * @return Collection<int, SplFileInfo>
     */
    protected function getEntries(string $path, bool $includeFiles): Collection
    {
        $entries = collect(scandir($path) ?: [])
            ->reject(static fn (string $name): bool => $name === '.' || $name === '..')
            ->map(function (string $name) use ($path): SplFileInfo {
                return new SplFileInfo(
                    rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name
                );
            })
            ->filter(function (SplFileInfo $entry) use ($includeFiles): bool {
                if (! $includeFiles && ! $entry->isDir()) {
                    return false;
                }

                return $this->passesHiddenFilter($entry)
                    && $this->passesSymlinkFilter($entry)
                    && $this->passesExcludeNameFilter($entry);
            })
            ->sort($this->sortEntries(...))
            ->values();

        return $entries;
    }

    /**
     * Determine whether the given entry should be excluded from the result.
     *
     * @param  SplFileInfo  $entry
     * @param  array<int, string>  $excludedPaths
     * @return bool
     */
    protected function shouldExcludeEntry(SplFileInfo $entry, array $excludedPaths): bool
    {
        $entryPath = $this->normalizePath($entry->getPathname());

        foreach ($excludedPaths as $excludedPath) {
            if (
                $entryPath === $excludedPath ||
                str_starts_with($entryPath, $excludedPath . DIRECTORY_SEPARATOR)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the effective excluded paths for the current scan.
     *
     * This method applies package configuration such as vendor and
     * node_modules toggles before converting the paths into normalized
     * absolute paths.
     *
     * @param  string  $basePath
     * @param  array<int, string>  $excludedPaths
     * @return array<int, string>
     */
    protected function resolveExcludedPaths(string $basePath, array $excludedPaths): array
    {
        $paths = collect($excludedPaths);

        if ($this->shouldIncludeVendor()) {
            $paths = $paths->reject(
                fn (string $path): bool => $this->normalizeRelativePath($path) === 'vendor'
            );
        }

        if ($this->shouldIncludeNodeModules()) {
            $paths = $paths->reject(
                fn (string $path): bool => $this->normalizeRelativePath($path) === 'node_modules'
            );
        }

        return $paths
            ->map(fn (string $path): string => $this->toAbsolutePath($basePath, $path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Determine whether the entry passes the hidden-file filter.
     *
     * @param  SplFileInfo  $entry
     * @return bool
     */
    protected function passesHiddenFilter(SplFileInfo $entry): bool
    {
        if ($this->shouldIncludeHidden()) {
            return true;
        }

        return ! str_starts_with($entry->getFilename(), '.');
    }

    /**
     * Determine whether the entry passes the symbolic link filter.
     *
     * @param  SplFileInfo  $entry
     * @return bool
     */
    protected function passesSymlinkFilter(SplFileInfo $entry): bool
    {
        if ($this->shouldFollowSymlinks()) {
            return true;
        }

        return ! is_link($entry->getPathname());
    }

    /**
     * Determine whether the entry passes the exclude-by-name filter.
     *
     * @param  SplFileInfo  $entry
     * @return bool
     */
    protected function passesExcludeNameFilter(SplFileInfo $entry): bool
    {
        return ! in_array($entry->getFilename(), $this->excludedNames(), true);
    }

    /**
     * Sort entries according to package configuration.
     *
     * Directories may optionally be placed before files, and names may be
     * compared using case-sensitive or case-insensitive sorting.
     *
     * @param  SplFileInfo  $left
     * @param  SplFileInfo  $right
     * @return int
     */
    protected function sortEntries(SplFileInfo $left, SplFileInfo $right): int
    {
        if ($this->shouldSortDirectoriesFirst() && $left->isDir() !== $right->isDir()) {
            return $left->isDir() ? -1 : 1;
        }

        $leftName = $left->getFilename();
        $rightName = $right->getFilename();

        return $this->shouldUseCaseSensitiveSort()
            ? strcmp($leftName, $rightName)
            : strcasecmp($leftName, $rightName);
    }

    /**
     * Render the individual tree lines recursively.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, string>  $lines
     * @param  string  $prefix
     * @param  bool  $compact
     * @return void
     */
    protected function renderTreeLines(
        array $nodes,
        array &$lines,
        string $prefix = '',
        bool $compact = false
    ): void {
        $lastIndex = count($nodes) - 1;

        foreach ($nodes as $index => $node) {
            $isLast = $index === $lastIndex;
            $connector = $isLast ? '└── ' : '├── ';
            $suffix = ($node['type'] ?? null) === 'directory' ? '/' : '';

            $lines[] = $prefix . $connector . $node['name'] . $suffix;

            if (! empty($node['children']) && is_array($node['children'])) {
                $this->renderTreeLines(
                    $node['children'],
                    $lines,
                    $prefix . ($isLast ? '    ' : '│   '),
                    $compact
                );
            }
        }
    }

    /**
     * Convert a path into a normalized absolute path.
     *
     * @param  string  $basePath
     * @param  string  $path
     * @return string
     */
    protected function toAbsolutePath(string $basePath, string $path): string
    {
        $normalizedPath = $this->normalizeRelativePath($path);

        if ($this->isAbsolutePath($normalizedPath)) {
            return $this->normalizePath($normalizedPath);
        }

        return $this->normalizePath(
            rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalizedPath, DIRECTORY_SEPARATOR)
        );
    }

    /**
     * Normalize a path by resolving it where possible and standardizing
     * directory separators.
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
     * Normalize a relative path value.
     *
     * @param  string  $path
     * @return string
     */
    protected function normalizeRelativePath(string $path): string
    {
        return trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
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

    /**
     * Determine whether hidden files and directories should be included.
     *
     * @return bool
     */
    protected function shouldIncludeHidden(): bool
    {
        return (bool) config('project-map.include_hidden', false);
    }

    /**
     * Determine whether the vendor directory should be included.
     *
     * @return bool
     */
    protected function shouldIncludeVendor(): bool
    {
        return (bool) config('project-map.include_vendor', false);
    }

    /**
     * Determine whether the node_modules directory should be included.
     *
     * @return bool
     */
    protected function shouldIncludeNodeModules(): bool
    {
        return (bool) config('project-map.include_node_modules', false);
    }

    /**
     * Determine whether symbolic links should be followed.
     *
     * @return bool
     */
    protected function shouldFollowSymlinks(): bool
    {
        return (bool) config('project-map.follow_symlinks', false);
    }

    /**
     * Determine whether directories should be sorted before files.
     *
     * @return bool
     */
    protected function shouldSortDirectoriesFirst(): bool
    {
        return (bool) config('project-map.sort_directories_first', true);
    }

    /**
     * Determine whether case-sensitive sorting should be used.
     *
     * @return bool
     */
    protected function shouldUseCaseSensitiveSort(): bool
    {
        return (bool) config('project-map.case_sensitive_sort', false);
    }

    /**
     * Get the configured list of excluded names.
     *
     * @return array<int, string>
     */
    protected function excludedNames(): array
    {
        /** @var array<int, string> $excludedNames */
        $excludedNames = config('project-map.exclude_names', []);

        return $excludedNames;
    }
}