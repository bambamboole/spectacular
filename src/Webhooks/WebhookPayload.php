<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public string $id,
        public string $event,
        public array $body,
    ) {}
}
