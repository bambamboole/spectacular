<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\ModelStates;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<Order>
 */
abstract class OrderState extends State
{
    #[\Override]
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Shipped::class);
    }
}
