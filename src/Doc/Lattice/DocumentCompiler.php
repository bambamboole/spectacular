<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Lattice;

use Bambamboole\Spectacular\Doc\Model\ApiDocument;
use Bambamboole\Spectacular\Doc\Model\ApiGroup;
use Bambamboole\Spectacular\Doc\Model\Contract;
use Bambamboole\Spectacular\Doc\Model\HttpFacet;
use Bambamboole\Spectacular\Doc\Model\Operation;
use Bambamboole\Spectacular\Doc\Model\Param;
use Bambamboole\Spectacular\Doc\Model\ParamGroup;
use Illuminate\Support\Str;
use Lattice\Lattice\Core\Components\Badge;
use Lattice\Lattice\Core\Components\Component;
use Lattice\Lattice\Core\Components\Grid;
use Lattice\Lattice\Core\Components\Heading;
use Lattice\Lattice\Core\Components\Link;
use Lattice\Lattice\Core\Components\Section;
use Lattice\Lattice\Core\Components\Stack;
use Lattice\Lattice\Core\Components\Tab;
use Lattice\Lattice\Core\Components\Tabs;
use Lattice\Lattice\Core\Components\Text;
use Lattice\Lattice\Core\Enums\Color;

final class DocumentCompiler
{
    /**
     * @return list<Component>
     */
    public function compile(ApiDocument $document): array
    {
        $operationsById = [];
        foreach ($document->operations as $operation) {
            $operationsById[$operation->id] = $operation;
        }

        return [
            Grid::make()
                ->columns(2)
                ->schema([
                    $this->navColumn($document->groups, $operationsById),
                    $this->contentColumn($document->operations, $document->components),
                ]),
        ];
    }

    /**
     * @param  list<ApiGroup>  $groups
     * @param  array<string, Operation>  $operationsById
     */
    private function navColumn(array $groups, array $operationsById): Component
    {
        $children = [];

        foreach ($groups as $group) {
            $children[] = Heading::make($group->title, 4);
            $children[] = Stack::make()->schema($this->navLinks($group, $operationsById));
        }

        return Stack::make()->schema($children);
    }

    /**
     * @param  array<string, Operation>  $operationsById
     * @return list<Component>
     */
    private function navLinks(ApiGroup $group, array $operationsById): array
    {
        $links = [];

        foreach ($group->operationIds as $operationId) {
            $operation = $operationsById[$operationId] ?? null;

            if ($operation === null) {
                continue;
            }

            $links[] = Link::make($this->navLabel($operation))->href('#'.$operation->id);
        }

        return $links;
    }

    private function navLabel(Operation $operation): string
    {
        if ($operation->facet instanceof HttpFacet) {
            return $operation->facet->method.' '.$operation->facet->path;
        }

        return $operation->title;
    }

    /**
     * @param  list<Operation>  $operations
     * @param  array<string, mixed>  $components
     */
    private function contentColumn(array $operations, array $components): Component
    {
        return Stack::make()->schema(array_map(
            fn (Operation $operation): Component => $this->operationSection($operation, $components),
            $operations,
        ));
    }

    /**
     * @param  array<string, mixed>  $components
     */
    private function operationSection(Operation $operation, array $components): Component
    {
        $children = [$this->operationHeader($operation)];

        if ($operation->description !== null) {
            $children[] = Text::make($operation->description);
        }

        foreach ($operation->paramGroups as $paramGroup) {
            if ($paramGroup->params === []) {
                continue;
            }

            $children[] = $this->paramGroupSection($paramGroup);
        }

        $children[] = $this->responsesTabs($operation->responses, $components);

        return Section::make(title: $operation->title, key: $operation->id)
            ->schema($children);
    }

    private function operationHeader(Operation $operation): Component
    {
        $row = [];

        if ($operation->facet instanceof HttpFacet) {
            $row[] = Badge::make($operation->facet->method);
            $row[] = Text::make($operation->facet->path);
        }

        if ($operation->deprecated) {
            $row[] = Badge::make('Deprecated');
        }

        return Stack::make()->direction('row')->schema($row);
    }

    private function paramGroupSection(ParamGroup $paramGroup): Component
    {
        return Section::make(ucfirst($paramGroup->location).' parameters')
            ->schema([
                Stack::make()->schema(array_map(
                    fn (Param $param): Component => $this->paramRow($param),
                    $paramGroup->params,
                )),
            ]);
    }

    private function paramRow(Param $param): Component
    {
        $row = [
            Text::make($param->name),
            Badge::make($this->typeLabelFromSchema($param->schema)),
        ];

        if ($param->required) {
            $row[] = Text::make('*')->color(Color::Danger);
        }

        if ($param->description !== null) {
            $row[] = Text::make($param->description);
        }

        return Stack::make()->direction('row')->schema($row);
    }

    /**
     * @param  list<Contract>  $responses
     * @param  array<string, mixed>  $components
     */
    private function responsesTabs(array $responses, array $components): Component
    {
        $byStatus = [];
        foreach ($responses as $contract) {
            $status = $contract->status ?? 'default';
            $byStatus[$status] ??= $contract;
        }

        return Tabs::make()->schema(array_map(
            fn (string $status, Contract $contract): Component => Tab::make($status, $status)
                ->schema([$this->responseBody($contract, $components)]),
            array_keys($byStatus),
            array_values($byStatus),
        ));
    }

    /**
     * @param  array<string, mixed>  $components
     */
    private function responseBody(Contract $contract, array $components): Component
    {
        if ($contract->schema === []) {
            return Text::make('No body.');
        }

        return SchemaTree::make()->forSchema($contract->schema, $components);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function typeLabelFromSchema(array $schema): string
    {
        if (is_string($schema['$ref'] ?? null)) {
            return Str::afterLast($schema['$ref'], '/');
        }

        $type = $schema['type'] ?? null;

        if (is_array($type)) {
            $nonNull = array_values(array_filter($type, fn (mixed $t): bool => $t !== 'null'));
            $base = is_string($nonNull[0] ?? null) ? $nonNull[0] : 'mixed';

            return in_array('null', $type, true) ? $base.'?' : $base;
        }

        if ($type === 'array') {
            $items = $schema['items'] ?? [];

            return (is_array($items) ? $this->typeLabelFromSchema($items) : 'mixed').'[]';
        }

        if (is_string($type)) {
            return isset($schema['enum']) ? $type.' enum' : $type;
        }

        return isset($schema['enum']) ? 'enum' : 'mixed';
    }
}
