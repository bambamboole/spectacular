<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Data;

final class StoreGalleryData extends Data
{
    /**
     * @param  array<int, ImageBlockData>  $images
     */
    public function __construct(
        /** Gallery title. */
        public string $title,
        /** Images shown in the gallery. */
        public array $images = [],
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'images' => ['array'],
        ];
    }
}
