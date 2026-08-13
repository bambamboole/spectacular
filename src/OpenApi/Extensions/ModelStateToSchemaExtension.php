<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\ModelStates\State;

/**
 * Scramble has no notion of spatie/laravel-model-states, so a state-typed
 * resource property would otherwise document as an empty object while the
 * wire format is the state's morph name string.
 */
final class ModelStateToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && is_a($type->name, State::class, true);
    }

    /**
     * @param  ObjectType  $type
     */
    #[\Override]
    public function toSchema(Type $type): StringType
    {
        $schema = new StringType;
        $schema->enum(self::baseStateClass($type->name)::getStateMapping()->keys()->all());

        return $schema;
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', self::baseStateClass($type->name), $this->components);
    }

    /**
     * Properties are usually typed with the abstract base state, but a concrete
     * state must document under the same shared schema as its base.
     *
     * @return class-string<State<covariant Model>>
     */
    private static function baseStateClass(string $state): string
    {
        if (! is_a($state, State::class, true)) {
            throw new InvalidArgumentException("{$state} is not a model state class.");
        }

        while (($parent = get_parent_class($state)) !== false && is_subclass_of($parent, State::class)) {
            $state = $parent;
        }

        return $state;
    }
}
