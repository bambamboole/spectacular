<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Support;

use ReflectionClass;
use ReflectionMethod;
use Throwable;

trait InvokesZeroArgMethods
{
    /**
     * @param  ReflectionClass<object>  $class
     */
    private function publicZeroArgMethod(ReflectionClass $class, string $methodName): ?ReflectionMethod
    {
        if (! $class->hasMethod($methodName)) {
            return null;
        }

        $method = $class->getMethod($methodName);

        return $method->isPublic() && $method->getNumberOfRequiredParameters() === 0 ? $method : null;
    }

    /**
     * @param  ReflectionClass<object>  $class
     */
    private function invokeZeroArgMethod(ReflectionClass $class, string $methodName): mixed
    {
        $method = $this->publicZeroArgMethod($class, $methodName);

        if ($method === null) {
            return null;
        }

        try {
            return $method->invoke($class->newInstanceWithoutConstructor());
        } catch (Throwable) {
            return null;
        }
    }
}
