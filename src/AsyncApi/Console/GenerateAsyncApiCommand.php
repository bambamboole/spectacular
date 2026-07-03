<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Console;

use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\Console\AbstractGenerateDocumentCommand;
use JsonException;

final class GenerateAsyncApiCommand extends AbstractGenerateDocumentCommand
{
    protected $signature = 'spectacular:asyncapi
        {--path= : Write the JSON document to this path instead of stdout}
        {--pretty=true : Pretty print the JSON document}';

    protected $description = 'Generate an AsyncAPI document for documented Laravel broadcast events.';

    /**
     * @throws JsonException
     */
    public function handle(AsyncApiGenerator $generator): int
    {
        return $this->outputDocument($generator->generate());
    }
}
