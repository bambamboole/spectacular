<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Message
{
    /**
     * @param  list<string>  $channels
     * @param  list<string>  $tags
     */
    public function __construct(
        public array $channels = [],
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public array $tags = [],
        public ?string $payload = null,
    ) {}
}
