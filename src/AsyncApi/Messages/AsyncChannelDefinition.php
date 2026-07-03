<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Messages;

final readonly class AsyncChannelDefinition
{
    public function __construct(
        public string $key,
        public string $address,
        public string $kind = 'laravel',
    ) {}
}
