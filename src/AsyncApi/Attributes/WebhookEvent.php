<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class WebhookEvent extends Message
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $name,
        public string $payloadMethod = 'webhookPayload',
        public array $headers = [],
        ?string $title = null,
        ?string $summary = null,
        ?string $description = null,
        array $tags = [],
        ?string $payload = null,
    ) {
        parent::__construct(
            channels: [],
            title: $title,
            summary: $summary,
            description: $description,
            tags: $tags,
            payload: $payload,
        );
    }
}
