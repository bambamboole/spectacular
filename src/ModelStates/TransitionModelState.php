<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\ModelStates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;
use Spatie\ModelStates\State;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Executes an API-requested state transition: resolves the target state from
 * its morph name, validates the payload against the transition's Data class
 * when it declares one, rejects payloads on transitions that take none, and
 * renders a denied transition as 409 via StateTransitionDenied.
 */
final readonly class TransitionModelState
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Model $model, string $field, string $target, array $payload = []): Model
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

        $data = $this->payload($transition, $payload);

        try {
            return DB::transaction(fn (): Model => $data instanceof Data
                ? $state->transitionTo($targetClass, $data)
                : $state->transitionTo($targetClass));
        } catch (TransitionNotAllowed) {
            throw StateTransitionDenied::for($model, $from, $target, $transitions->allowedFrom($from));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payload(StateTransition $transition, array $payload): ?Data
    {
        if ($transition->dataClass === null) {
            if ($payload !== []) {
                throw ValidationException::withMessages([
                    'payload' => [__('spectacular::states.no-payload-accepted')],
                ]);
            }

            return null;
        }

        $this->rejectUnknownKeys($transition->dataClass, $payload);

        return $transition->dataClass::validateAndCreate($payload);
    }

    /**
     * Plain array payloads bypass whatever strictness a Data class applies to
     * request payloads, so typos and attempts to write undeclared fields must
     * fail loudly here instead of being silently discarded.
     *
     * @param  class-string<Data>  $dataClass
     * @param  array<string, mixed>  $payload
     */
    private function rejectUnknownKeys(string $dataClass, array $payload): void
    {
        $allowed = [];

        foreach (app(DataConfig::class)->getDataClass($dataClass)->properties as $property) {
            $allowed[$property->name] = true;

            if ($property->inputMappedName !== null) {
                $allowed[$property->inputMappedName] = true;
            }
        }

        $unknown = array_values(array_filter(
            array_map(strval(...), array_keys($payload)),
            fn (string $key): bool => ! isset($allowed[$key]),
        ));

        if ($unknown !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unknown, [__('spectacular::states.unknown-property')]),
            );
        }
    }
}
