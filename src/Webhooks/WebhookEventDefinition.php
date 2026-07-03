<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;

final readonly class WebhookEventDefinition
{
    /**
     * @param  class-string  $class
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $name,
        public string $class,
        public ?string $title,
        public ?string $summary,
        public ?string $description,
        public array $tags,
        public WebhookEvent $attribute,
    ) {}
}
