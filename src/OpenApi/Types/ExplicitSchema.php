<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Types;

use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;

final class ExplicitSchema extends Schema
{
    /** @param array<string, mixed> $definition */
    public function __construct(private readonly array $definition)
    {
        $this->type = new UnknownType;
    }

    public function serializeAs31(): mixed
    {
        return $this->definition;
    }

    public function serializeAs32(): mixed
    {
        return $this->definition;
    }
}
