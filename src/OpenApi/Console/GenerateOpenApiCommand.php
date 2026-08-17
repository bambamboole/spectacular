<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Console;

use Bambamboole\Spectacular\Console\AbstractGenerateDocumentCommand;
use Bambamboole\Spectacular\OpenApi\PublicOpenApiDocument;
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
        $publicDocument = PublicOpenApiDocument::create($document);

        if (is_string($path) && $path !== '' && $publicDocument !== null) {
            $this->writeDocument($publicDocument, $this->publicPath($path));
        }

        return $result;
    }

    private function publicPath(string $path): string
    {
        if (str_ends_with($path, '.json')) {
            return substr($path, 0, -5).'.public.json';
        }

        return $path.'.public.json';
    }
}
