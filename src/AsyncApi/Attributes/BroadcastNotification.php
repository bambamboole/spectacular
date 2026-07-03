<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class BroadcastNotification extends Message
{
    /**
     * @param  list<class-string>  $notifiables
     * @param  list<string>  $channels
     * @param  list<string>  $tags
     */
    public function __construct(
        public array $notifiables = [],
        array $channels = [],
        ?string $title = null,
        ?string $summary = null,
        ?string $description = null,
        array $tags = [],
        ?string $payload = null,
    ) {
        parent::__construct(
            channels: $channels,
            title: $title,
            summary: $summary,
            description: $description,
            tags: $tags,
            payload: $payload,
        );
    }
}
