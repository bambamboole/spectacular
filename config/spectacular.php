<?php
declare(strict_types=1);

return [
    'openapi' => [
        'validation' => [
            'path' => base_path('openapi.json'),
        ],

        /*
         * The document's info object. Scramble already resolves the title from
         * `scramble.ui.title` and the version and description from `scramble.info`;
         * everything set here wins over those, and what OpenAPI offers beyond them
         * is only available here.
         *
         *  'info' => [
         *      'title' => 'Acme API',
         *      'version' => '2.1.0',
         *      'description' => 'What this API is for.',
         *      'terms_of_service' => 'https://acme.test/terms',
         *      'contact' => ['name' => 'API support', 'email' => 'api@acme.test', 'url' => 'https://acme.test/support'],
         *      // An SPDX `identifier` or a `url`, never both.
         *      'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
         *  ],
         */
        'info' => [],

        /*
         * How the documented API is rate limited. A route carrying one of the
         * `middleware` patterns documents `headers` on its success responses and a
         * shared 429 response carrying `exhausted_headers` on top of them.
         *
         * Header values are documented as integers. Leave `middleware` or `headers`
         * empty to document rate limiting yourself.
         */
        'rate_limiting' => [
            'middleware' => ['throttle', 'throttle:*'],
            'headers' => [
                'X-RateLimit-Limit' => 'The maximum number of requests allowed in the current window.',
                'X-RateLimit-Remaining' => 'The number of requests left in the current window.',
            ],
            'exhausted_headers' => [
                'Retry-After' => 'Seconds to wait before sending another request.',
                'X-RateLimit-Reset' => 'Seconds until the current window resets.',
            ],
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

        /*
         * Where to discover base state classes carrying the StateEndpoint
         * attribute. Each annotated class declares a templated
         * spatie/laravel-model-states transition route that is fanned out
         * into one documented operation per reachable target state.
         *
         * A transition declares its request body by taking a laravel-data
         * object in its custom Transition constructor after the model; the
         * documented operation then requires exactly that body.
         *
         *  'state_transitions' => [
         *      'scan_paths' => [app_path('States')],
         *  ],
         */
        'state_transitions' => [
            'scan_paths' => [],
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
