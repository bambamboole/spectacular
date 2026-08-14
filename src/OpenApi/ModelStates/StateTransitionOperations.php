<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\ModelStates;

use Bambamboole\Spectacular\Attributes\StateEndpoint;
use Bambamboole\Spectacular\ModelStates\ModelStateTransitions;
use Bambamboole\Spectacular\ModelStates\StateTransition;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataSchemaFactory;
use Bambamboole\Spectacular\Support\ClassDiscoverer;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use ReflectionClass;
use RuntimeException;
use Spatie\LaravelData\Data;
use Spatie\ModelStates\State;

/**
 * Fans a templated state transition route out into one documented operation
 * per reachable target state, each carrying the exact request body its
 * transition expects. Endpoints are declared with the StateEndpoint attribute
 * on a base state class, discovered from the configured scan paths. Runs as a
 * document transformer because it must clone the finished generic operation
 * (security, shared error responses, rate-limit headers, path parameters, and
 * the resource response are already attached) — operation transformers can
 * only mutate the single operation Scramble builds per route.
 */
final readonly class StateTransitionOperations implements DocumentTransformer
{
    public function __construct(
        private TypeTransformer $typeTransformer,
        private ClassDiscoverer $classes,
    ) {}

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($this->stateEndpoints() as [$stateClass, $endpoint]) {
            $this->expand($document, $stateClass, $endpoint);
        }
    }

    /**
     * @return list<array{class-string<State<Model>>, StateEndpoint}>
     */
    private function stateEndpoints(): array
    {
        /** @var list<string> $scanPaths */
        $scanPaths = config('spectacular.openapi.state_transitions.scan_paths', []);
        $endpoints = [];

        foreach ($this->classes->classesIn($scanPaths) as $class) {
            $attributes = new ReflectionClass($class)->getAttributes(StateEndpoint::class);

            if ($attributes === []) {
                continue;
            }

            if (! is_subclass_of($class, State::class)) {
                throw new LogicException("[{$class}] carries the StateEndpoint attribute but is not a model state class.");
            }

            $endpoints[] = [$class, $attributes[0]->newInstance()];
        }

        return $endpoints;
    }

    /**
     * @param  class-string<State<Model>>  $stateClass
     */
    private function expand(OpenApi $document, string $stateClass, StateEndpoint $endpoint): void
    {
        $label = $endpoint->label ?? self::labelFor($stateClass);
        [$index, $generic] = $this->findTemplatedOperation($document, $endpoint->path, $endpoint->method);

        $transitions = ModelStateTransitions::forStateClass($stateClass);
        $conflict = $this->conflictResponse($document->components, $label, $transitions);
        $bodyFactory = new DataSchemaFactory($this->typeTransformer);
        $paths = [];

        foreach ($transitions->stateMapping()->keys()->sort()->values() as $target) {
            $sources = $transitions->into($target);

            if ($sources === []) {
                continue;
            }

            $operation = clone $generic;
            $operation->setPath(str_replace('{state}', $target, $endpoint->path));
            $operation->setOperationId("{$generic->operationId}-to.{$target}");
            $operation->summary("Transition {$label} to {$target}");
            $operation->description($this->describe($label, $target, $sources));
            $operation->parameters = array_values(array_filter(
                $operation->parameters,
                fn (Parameter|Reference $parameter): bool => ! ($parameter instanceof Parameter && $parameter->in === 'path' && $parameter->name === 'state'),
            ));

            $dataClass = $this->dataClass($sources);
            $operation->requestBodyObject = $dataClass !== null
                ? $bodyFactory->requestBody($dataClass, $document->components)
                : null;
            $operation->responses = [...$operation->responses ?? [], $conflict];

            $paths[] = Path::make($operation->path)->addOperation($operation);
        }

        array_splice($document->paths, $index, 1, $paths);
    }

    /**
     * @param  class-string<State<Model>>  $stateClass
     */
    private static function labelFor(string $stateClass): string
    {
        $basename = class_basename($stateClass);
        $trimmed = preg_replace('/State$/', '', $basename);

        return (string) str($trimmed === null || $trimmed === '' ? $basename : $trimmed)->headline()->lower();
    }

    /**
     * @return array{int, Operation}
     */
    private function findTemplatedOperation(OpenApi $document, string $template, string $method): array
    {
        foreach ($document->paths as $index => $path) {
            if ($path->path !== $template) {
                continue;
            }

            $operation = $path->operations[$method] ?? throw new RuntimeException("No {$method} operation generated for [{$template}].");

            return [$index, $operation];
        }

        throw new RuntimeException("State transition route [{$template}] is not part of the generated document.");
    }

    /**
     * @param  list<StateTransition>  $sources
     */
    private function describe(string $label, string $target, array $sources): string
    {
        $from = implode('` or `', array_map(fn (StateTransition $transition): string => $transition->from, $sources));

        return "Transition the {$label} to `{$target}`. Allowed while the {$label} is `{$from}`. "
            .'Responds `409 Conflict` when the current state does not allow this transition.';
    }

    /**
     * Every source state of a target must agree on the payload: a single
     * documented operation cannot carry a different request body per current
     * state.
     *
     * @param  list<StateTransition>  $sources
     * @return class-string<Data>|null
     */
    private function dataClass(array $sources): ?string
    {
        $dataClasses = array_unique(array_map(fn (StateTransition $transition): ?string => $transition->dataClass, $sources));

        if (count($dataClasses) > 1) {
            $target = $sources[0]->to;

            throw new LogicException("Transitions into [{$target}] disagree on their payload data class.");
        }

        return $dataClasses[0];
    }

    private function conflictResponse(Components $components, string $label, ModelStateTransitions $transitions): Reference
    {
        $reference = new Reference('responses', str($label)->studly().'TransitionDenied', $components);

        if ($components->has($reference)) {
            return $reference;
        }

        // Scramble registers type-based schemas under their context-unique
        // short name, so the shared state enum lives at the class basename.
        $stateSchemaKey = class_basename($transitions->baseStateClass());

        if (! $components->hasSchema($stateSchemaKey)) {
            $states = new StringType;
            $states->enum($transitions->stateMapping()->keys()->all());
            $components->addSchema($stateSchemaKey, Schema::fromType($states));
        }

        $stateReference = fn (): Reference => new Reference('schemas', $stateSchemaKey, $components);

        $body = new ObjectType()
            ->addProperty('message', new StringType()->setDescription('Human-readable explanation of the denied transition.'))
            ->addProperty('current_state', $stateReference()->setDescription('State the record is in right now.'))
            ->addProperty('requested_state', $stateReference()->setDescription('State the request tried to move the record to.'))
            ->addProperty('allowed_states', new ArrayType()->setItems($stateReference())->setDescription('States reachable from the current state.'))
            ->setRequired(['message', 'current_state', 'requested_state', 'allowed_states']);

        $components->add($reference, Response::make(409)
            ->setDescription('State transition not allowed')
            ->setContent('application/json', Schema::fromType($body)));

        return $reference;
    }
}
