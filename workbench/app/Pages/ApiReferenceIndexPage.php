<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Http\Page;
use Lattice\Lattice\Ui\Components\Button;
use Lattice\Lattice\Ui\Components\Stack;
use Lattice\Lattice\Ui\Enums\Gap;
use Lattice\Lattice\Ui\Enums\StackDirection;

#[AsPage(route: '/', name: 'home')]
final class ApiReferenceIndexPage extends Page
{
    public function render(PageSchema $schema): PageSchema
    {
        return $schema
            ->title('API reference layouts')
            ->schema([
                Stack::make()
                    ->direction(StackDirection::Row)
                    ->gap(Gap::Medium)
                    ->schema([
                        Button::make('Sidebar')->href('/docs'),
                        Button::make('Stacked')->href('/docs/stacked'),
                    ]),
            ]);
    }
}
