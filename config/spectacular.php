<?php
declare(strict_types=1);

return [
    'openapi' => [
        'validation' => [
            'path' => base_path('openapi.json'),
        ],

        /*
         * How the documented API is authenticated. Consumers of a public reference
         * have no session to borrow a token from, so the document has to state the
         * modes they can use.
         *
         * Every entry under `schemes` becomes an entry in `components.securitySchemes`
         * and a document-level requirement; several entries read as alternatives.
         * Leave `schemes` empty to document security yourself.
         *
         * Routes carrying none of the `middleware` patterns are documented as public.
         *
         *  'schemes' => [
         *      'bearer' => [
         *          'type' => 'http',
         *          'scheme' => 'bearer',
         *          'description' => 'A personal access token.',
         *      ],
         *      'oauth2' => [
         *          'type' => 'oauth2',
         *          'flows' => [
         *              'authorizationCode' => [
         *                  'authorization_url' => '/oauth/authorize',
         *                  'token_url' => '/oauth/token',
         *                  // Literal scopes, or an invokable class-string resolving
         *                  // them from the app (a cached config cannot hold closures).
         *                  'scopes' => ['users:read' => 'Read users'],
         *              ],
         *              'clientCredentials' => ['token_url' => '/oauth/token'],
         *          ],
         *      ],
         *      'oidc' => [
         *          'type' => 'openIdConnect',
         *          'url' => '/.well-known/openid-configuration',
         *      ],
         *      'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'],
         *  ],
         */
        'security' => [
            'middleware' => ['auth', 'auth:*'],
            'schemes' => [],
        ],
    ],
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
