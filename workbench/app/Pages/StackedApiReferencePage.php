<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Lattice\Lattice\Attributes\AsPage;

#[AsPage(route: 'docs/stacked', name: 'docs.stacked')]
final class StackedApiReferencePage extends ApiReferencePage
{
    protected function referenceLayout(): string
    {
        return 'stacked';
    }
}
