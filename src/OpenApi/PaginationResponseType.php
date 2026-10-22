<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

use Bambamboole\Spectacular\PaginationMode;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Str;

final class PaginationResponseType extends Type
{
    public function __construct(
        private PaginationMode $mode,
        private ObjectType $schema,
    ) {
        parent::__construct('object');
    }

    public function clone(): static
    {
        $clone = parent::clone();
        $clone->schema = $this->schema->clone();

        return $clone;
    }

    public function serializeAs31(): mixed
    {
        return $this->titled($this->schema->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->titled($this->schema->serializeAs32());
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function titled(array $schema): array
    {
        return [
            'title' => Str::headline($this->mode->value).' pagination',
            ...$schema,
        ];
    }
}
