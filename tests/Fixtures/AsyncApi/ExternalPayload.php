<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

final class ExternalPayload
{
    public function __construct(
        public string $value,
    ) {}
}
