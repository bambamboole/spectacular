<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\ModelStates;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property OrderState $status
 */
class Order extends Model
{
    use HasStates;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderState::class,
        ];
    }
}
