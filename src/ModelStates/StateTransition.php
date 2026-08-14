<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\ModelStates;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\ModelStates\State;
use Spatie\ModelStates\Transition;

final readonly class StateTransition
{
    /**
     * @param  class-string<State<Model>>  $toStateClass
     * @param  class-string<Transition>|null  $transitionClass
     * @param  class-string<Data>|null  $dataClass
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $toStateClass,
        public ?string $transitionClass,
        public ?string $dataClass,
    ) {}
}
