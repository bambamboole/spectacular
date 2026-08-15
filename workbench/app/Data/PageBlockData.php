<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Bambamboole\Spectacular\Attributes\SpecOneOf;
use Spatie\LaravelData\Attributes\PropertyForMorph;
use Spatie\LaravelData\Contracts\PropertyMorphableData;
use Spatie\LaravelData\Data;
use Workbench\App\Enums\BlockType;

#[SpecOneOf(['text' => TextBlockData::class, 'image' => ImageBlockData::class])]
abstract class PageBlockData extends Data implements PropertyMorphableData
{
    public function __construct(
        #[PropertyForMorph]
        public BlockType $type,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function morph(array $properties): ?string
    {
        $type = $properties['type'] ?? null;

        if (! $type instanceof BlockType) {
            $type = BlockType::tryFrom(is_string($type) ? $type : '');
        }

        return match ($type) {
            BlockType::Text => TextBlockData::class,
            BlockType::Image => ImageBlockData::class,
            default => null,
        };
    }
}
