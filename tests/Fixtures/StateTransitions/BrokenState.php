<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<BrokenModel>
 */
abstract class BrokenState extends State
{
    #[\Override]
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(BrokenOpen::class)
            ->allowTransition(BrokenOpen::class, BrokenDone::class, MisdeclaredTransition::class);
    }
}
