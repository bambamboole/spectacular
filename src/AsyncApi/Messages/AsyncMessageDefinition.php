<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Messages;

final readonly class AsyncMessageDefinition
{
    /**
     * @param  list<AsyncChannelDefinition>  $channels
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public string $key,
        public string $name,
        public array $channels,
        public array $message,
    ) {}
}
