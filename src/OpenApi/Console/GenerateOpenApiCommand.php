<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Console;

use Bambamboole\Spectacular\Console\AbstractGenerateDocumentCommand;
use Bambamboole\Spectacular\OpenApi\GroupedOpenApiDocuments;
use Dedoc\Scramble\Generator;
use JsonException;

final class GenerateOpenApiCommand extends AbstractGenerateDocumentCommand
{
    protected $signature = 'spectacular:openapi
        {--path= : Write the JSON document to this path instead of stdout}
        {--pretty=true : Pretty print the JSON document}';

    protected $description = 'Generate an OpenAPI document.';

    /**
     * @throws JsonException
     */
    public function handle(Generator $generator): int
    {
        $document = $generator();

        /** @var array<string, mixed> $document */
        $document = is_array($document) ? $document : [];

        $result = $this->outputDocument($document);
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            foreach (GroupedOpenApiDocuments::create($document) as $group => $groupDocument) {
                $this->writeDocument($groupDocument, $this->groupPath($path, (string) $group));
            }
        }

        return $result;
    }

    private function groupPath(string $path, string $group): string
    {
        $base = str_ends_with($path, '.json') ? substr($path, 0, -5) : $path;

        return $base.'.'.rawurlencode($group).'.json';
    }
}
