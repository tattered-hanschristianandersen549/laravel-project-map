<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Scan Path
    |--------------------------------------------------------------------------
    |
    | This is the default base path that will be scanned when no explicit
    | path is provided to the Artisan command.
    |
    | In most Laravel projects, this should remain as base_path().
    |
    */

    'default_path' => base_path(),

    /*
    |--------------------------------------------------------------------------
    | Default Maximum Depth
    |--------------------------------------------------------------------------
    |
    | This value determines how many directory levels should be traversed
    | when generating the project map.
    |
    | Example:
    | - 1 = root level only
    | - 2 = root + one nested level
    | - 5 = deeper project inspection
    |
    */

    'default_depth' => 5,

    /*
    |--------------------------------------------------------------------------
    | Include Files
    |--------------------------------------------------------------------------
    |
    | Determines whether files should be included in the generated output.
    |
    | When set to false, only directories will be listed.
    | When set to true, both directories and files will be included.
    |
    */

    'include_files' => false,

    /*
    |--------------------------------------------------------------------------
    | Hidden Files and Directories
    |--------------------------------------------------------------------------
    |
    | Determines whether hidden files and directories should be included
    | in the generated output.
    |
    | This affects dot-prefixed paths such as:
    | - .git
    | - .idea
    | - .vscode
    | - .env.example
    |
    */

    'include_hidden' => false,

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | These paths will be excluded from the generated project map by default.
    |
    | The values may be relative to the project base path. Common heavy or
    | generated directories are excluded to keep the output clean and fast.
    |
    */

    'exclude' => [
        'vendor',
        'node_modules',
        '.git',
        'storage/logs',
        'bootstrap/cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Directory Toggles
    |--------------------------------------------------------------------------
    |
    | These toggles provide a more expressive way for users to control whether
    | commonly excluded heavy directories should be scanned.
    |
    | If enabled, the related path may be removed from exclusion internally
    | before the scan begins.
    |
    */

    'include_vendor' => false,

    'include_node_modules' => false,

    /*
    |--------------------------------------------------------------------------
    | Exclude By Name
    |--------------------------------------------------------------------------
    |
    | These directory or file names will be excluded anywhere they appear
    | in the scanned structure.
    |
    | This is useful for filtering common generated artifacts globally
    | without specifying full paths.
    |
    */

    'exclude_names' => [
        '.DS_Store',
        'Thumbs.db',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sort Directories First
    |--------------------------------------------------------------------------
    |
    | When enabled, directories will be listed before files.
    | This usually makes the generated structure easier to read.
    |
    */

    'sort_directories_first' => true,

    /*
    |--------------------------------------------------------------------------
    | Case Sensitive Sorting
    |--------------------------------------------------------------------------
    |
    | Determines whether names should be sorted with case sensitivity.
    | In most cases, false produces more user-friendly output.
    |
    */

    'case_sensitive_sort' => false,

    /*
    |--------------------------------------------------------------------------
    | Follow Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Determines whether symbolic links should be followed during traversal.
    |
    | This is disabled by default to avoid unexpected recursion or scanning
    | outside the intended project boundary.
    |
    */

    'follow_symlinks' => false,

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    |
    | Defines the default output format when no format option is supplied.
    |
    | Supported values:
    | - text
    | - json
    |
    */

    'output' => [
        'default_format' => 'text',

        /*
        |--------------------------------------------------------------------------
        | Save Path
        |--------------------------------------------------------------------------
        |
        | Optional default save location for generated project maps.
        | Leave as null to print only to the console unless --save is used.
        |
        */

        'default_save_path' => null,
    ],
];