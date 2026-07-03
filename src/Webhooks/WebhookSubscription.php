<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

final readonly class WebhookSubscription
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $url,
        public ?string $secret = null,
        public array $headers = [],
        public ?string $id = null,
    ) {}
}
