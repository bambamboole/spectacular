<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;

final class ExplicitOperation extends Operation
{
    /** @param array<string, mixed> $overrides */
    public function __construct(Operation $operation, private readonly array $overrides)
    {
        parent::__construct($operation->method);

        $this->mergeAttributes($operation->attributes());
        $this->mergeExtensionProperties($operation->extensionProperties());

        foreach (get_object_vars($operation) as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function serializeAs31(): mixed
    {
        return $this->applyOverrides(parent::serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->applyOverrides(parent::serializeAs32());
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function applyOverrides(array $result): array
    {
        foreach ($this->overrides as $key => $value) {
            if ($value === null) {
                unset($result[$key]);
            } elseif ($key === 'responses') {
                $result[$key] = array_filter(array_replace($result[$key] ?? [], $value), fn ($response): bool => $response !== null);
            } elseif ($key === 'parameters') {
                $parameters = [];
                foreach ([...($result[$key] ?? []), ...$value] as $parameter) {
                    $id = ($parameter['in'] ?? '').':'.($parameter['name'] ?? $parameter['$ref'] ?? '');
                    if (($parameter['x-remove'] ?? false) === true) {
                        unset($parameters[$id]);
                    } else {
                        $parameters[$id] = $parameter;
                    }
                }
                $result[$key] = array_values($parameters);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
