<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            /*
             * Where uploaded media actually sits.
             *
             * Laravel's convention is `storage/app/public` reached through a
             * symlink from `public/storage`. That is right everywhere the
             * symlink can be made — and it cannot be made on the current
             * shared host, where `symlink()` is in `disable_functions`. With
             * no link and no override, every media URL 404s: the site keeps
             * working and every image on it disappears.
             *
             * So the root is an environment value, not a code constant. The
             * host sets `MEDIA_DISK_ROOT` in `.env` and writes files straight
             * into the served directory; everywhere else the default holds and
             * `storage:link` behaves as normal. Previously the server carried a
             * hand edit to this file, which meant the next deploy that touched
             * config/ silently restored the 404s.
             *
             * `?:` and not `??`: an empty MEDIA_DISK_ROOT= line yields '', and
             * an empty root would resolve every media path to the filesystem
             * root. Same reason as CRM_DRIVER in config/crm.php.
             */
            'root' => env('MEDIA_DISK_ROOT') ?: storage_path('app/public'),
            /*
             * Root-relative on purpose. Laravel's default bakes APP_URL into
             * every uploaded-file URL, so the moment the site is reached on a
             * different host — a dev port, staging, or the domain move in §16
             * — every image on the site breaks. A relative path is correct on
             * every host and needs no environment to be right.
             *
             * Anything that genuinely needs an absolute URL (og:image) builds
             * it from the actual request; see Services\Seo\MetaBuilder.
             */
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
