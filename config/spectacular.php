<?php
declare(strict_types=1);

use Bambamboole\Spectacular\OpenApi\Extensions\PaginationExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\QueryBuilderExtension;

return [
    'asyncapi' => [
        'version' => '3.0.0',
        'default_content_type' => 'application/json',
        'info' => [
            'title' => env('APP_NAME', 'Laravel').' AsyncAPI',
            'version' => env('APP_VERSION', '0.0.1'),
        ],
        'laravel_extensions' => true,
        'scan_paths' => [
            app_path('Events'),
        ],
    ],

    'scramble' => [
        'extensions' => [
            QueryBuilderExtension::class,
            PaginationExtension::class,
        ],
    ],
];
