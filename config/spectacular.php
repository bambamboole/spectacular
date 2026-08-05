<?php
declare(strict_types=1);

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
        'webhooks' => [
            'channel' => [
                'key' => 'webhooks',
                'address' => '{webhookUrl}',
            ],
            'headers' => [
                'Content-Type' => ['type' => 'string', 'enum' => ['application/json']],
                'Signature' => ['type' => 'string'],
                'Timestamp' => ['type' => 'integer'],
            ],
        ],
    ],
];
