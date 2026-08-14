<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Spatie\ModelStates\Transition;

/**
 * Violates the transition constructor contract: a required parameter that is
 * neither the model nor a Data payload.
 */
final class MisdeclaredTransition extends Transition
{
    public function __construct(
        private readonly BrokenModel $model,
        private readonly string $note,
    ) {}

    public function handle(): BrokenModel
    {
        $this->model->status = new BrokenDone($this->model);
        $this->model->note = $this->note;
        $this->model->save();

        return $this->model;
    }
}
