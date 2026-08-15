<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Brick\Math\BigDecimal;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class StoreProductData extends Data
{
    public function __construct(
        /** Product name. */
        public string $name,
        /** Net price per unit. */
        public BigDecimal $price,
        /** Discount on the product in percent. */
        #[Max(100), MapInputName('discount_percent')]
        public Optional|BigDecimal $discountPercent,
        /** Package weight in kilograms. */
        public ?BigDecimal $weight = null,
    ) {}
}
