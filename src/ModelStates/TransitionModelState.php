<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\ModelStates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;
use Spatie\ModelStates\State;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Executes an API-requested state transition: resolves the target state from
 * its morph name, validates the request body against the transition's Data
 * class when it declares one, rejects bodies on transitions that take none,
 * and renders a denied transition as 409 via StateTransitionDenied.
 */
final readonly class TransitionModelState
{
    public function handle(Model $model, string $field, string $target, Request $request): Model
    {
        $transitions = ModelStateTransitions::for($model::class, $field);
        $targetClass = $transitions->resolveStateClass($target);

        if ($targetClass === null) {
            throw new NotFoundHttpException;
        }

        $state = $model->{$field};

        if (! $state instanceof State) {
            throw new NotFoundHttpException;
        }

        $from = $state->getValue();
        $transition = $transitions->find($from, $target);

        if (! $transition instanceof StateTransition) {
            throw StateTransitionDenied::for($model, $from, $target, $transitions->allowedFrom($from));
        }

        $data = $this->payload($transition, $request);

        try {
            return DB::transaction(fn (): Model => $data instanceof Data
                ? $state->transitionTo($targetClass, $data)
                : $state->transitionTo($targetClass));
        } catch (TransitionNotAllowed) {
            throw StateTransitionDenied::for($model, $from, $target, $transitions->allowedFrom($from));
        }
    }

    private function payload(StateTransition $transition, Request $request): ?Data
    {
        if ($transition->dataClass === null) {
            if ($request->json()->all() !== []) {
                throw ValidationException::withMessages([
                    'payload' => [__('spectacular::states.no-payload-accepted')],
                ]);
            }

            return null;
        }

        return $transition->dataClass::from($request);
    }
}
