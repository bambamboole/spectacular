<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Console;

use Bambamboole\Spectacular\Console\AbstractGenerateDocumentCommand;
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

        return $this->outputDocument($document);
    }
}
