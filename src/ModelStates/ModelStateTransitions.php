<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\ModelStates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use Spatie\LaravelData\Data;
use Spatie\ModelStates\DefaultTransition;
use Spatie\ModelStates\State;
use Spatie\ModelStates\Transition;

/**
 * Introspects a model's spatie/laravel-model-states configuration: which
 * transitions exist, which custom Transition class serves each pair, and
 * which Data class (if any) that class expects as request payload.
 */
final readonly class ModelStateTransitions
{
    /**
     * @param  class-string<State<Model>>  $baseStateClass
     * @param  list<StateTransition>  $transitions
     */
    private function __construct(
        private string $baseStateClass,
        private array $transitions,
    ) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass, string $field = 'status'): self
    {
        $cast = (new $modelClass)->getCasts()[$field] ?? null;

        if (! is_string($cast) || ! is_subclass_of($cast, State::class)) {
            throw new InvalidArgumentException("[{$field}] on [{$modelClass}] is not a model-state field.");
        }

        return new self($cast, self::resolveTransitions($cast));
    }

    /**
     * @return class-string<State<Model>>
     */
    public function baseStateClass(): string
    {
        return $this->baseStateClass;
    }

    /**
     * @return Collection<string, class-string<State<Model>>>
     */
    public function stateMapping(): Collection
    {
        /** @var Collection<string, class-string<State<Model>>> */
        return $this->baseStateClass::getStateMapping();
    }

    /**
     * @return list<StateTransition>
     */
    public function all(): array
    {
        return $this->transitions;
    }

    /**
     * @return list<StateTransition>
     */
    public function into(string $target): array
    {
        return array_values(array_filter($this->transitions, fn (StateTransition $transition): bool => $transition->to === $target));
    }

    public function find(string $from, string $to): ?StateTransition
    {
        return array_find($this->transitions, fn (StateTransition $transition): bool => $transition->from === $from && $transition->to === $to);
    }

    /**
     * @return list<string>
     */
    public function allowedFrom(string $from): array
    {
        return array_values(array_map(
            fn (StateTransition $transition): string => $transition->to,
            array_filter($this->transitions, fn (StateTransition $transition): bool => $transition->from === $from),
        ));
    }

    /**
     * @return class-string<State<Model>>|null
     */
    public function resolveStateClass(string $name): ?string
    {
        $stateClass = $this->baseStateClass::resolveStateClass($name);

        // resolveStateClass() falls back to returning the raw input string
        // for names outside the state mapping.
        if (! is_string($stateClass) || ! is_subclass_of($stateClass, $this->baseStateClass)) {
            return null;
        }

        return $stateClass;
    }

    /**
     * @param  class-string<State<Model>>  $baseStateClass
     * @return list<StateTransition>
     */
    private static function resolveTransitions(string $baseStateClass): array
    {
        $mapping = $baseStateClass::getStateMapping();
        $transitions = [];

        foreach ($baseStateClass::config()->allowedTransitions as $pair => $transitionClass) {
            [$from, $to] = explode('->', $pair, 2);
            $toStateClass = $mapping->get($to);

            if (! is_string($toStateClass) || ! is_subclass_of($toStateClass, $baseStateClass)) {
                continue;
            }

            $transitions[] = new StateTransition(
                from: $from,
                to: $to,
                toStateClass: $toStateClass,
                transitionClass: $transitionClass,
                dataClass: self::dataClassOf($transitionClass),
            );
        }

        return $transitions;
    }

    /**
     * A custom transition may take the payload as a single Data-typed
     * constructor parameter after the model; every other trailing parameter
     * must be optional. DefaultTransition subclasses are constructed by the
     * vendor with (model, field, newState) and never carry payload.
     *
     * @param  class-string<Transition>|null  $transitionClass
     * @return class-string<Data>|null
     */
    private static function dataClassOf(?string $transitionClass): ?string
    {
        if ($transitionClass === null || is_subclass_of($transitionClass, DefaultTransition::class)) {
            return null;
        }

        $constructor = new ReflectionClass($transitionClass)->getConstructor();
        $parameters = array_slice($constructor?->getParameters() ?? [], 1);
        $dataClass = null;

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

            if ($typeName !== null && is_subclass_of($typeName, Data::class)) {
                if ($dataClass !== null) {
                    throw new LogicException("[{$transitionClass}] declares more than one Data constructor parameter.");
                }

                $dataClass = $typeName;

                continue;
            }

            if (! $parameter->isOptional()) {
                throw new LogicException("[{$transitionClass}] has a required constructor parameter [{$parameter->getName()}] that is neither the model nor a Data payload.");
            }
        }

        return $dataClass;
    }
}
