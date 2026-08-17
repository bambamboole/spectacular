<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;

abstract class AbstractGenerateDocumentCommand extends Command
{
    /**
     * @param  array<string, mixed>  $document
     *
     * @throws JsonException
     */
    protected function outputDocument(array $document): int
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            $this->writeDocument($document, $path);

            return self::SUCCESS;
        }

        $this->line($this->encodeDocument($document));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws JsonException
     */
    protected function writeDocument(array $document, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->encodeDocument($document));
    }

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws JsonException
     */
    private function encodeDocument(array $document): string
    {
        return json_encode(
            $document,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($this->isPretty() ? JSON_PRETTY_PRINT : 0),
        ).PHP_EOL;
    }

    private function isPretty(): bool
    {
        $pretty = $this->option('pretty');

        if (is_string($pretty)) {
            return filter_var($pretty, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return true;
    }
}
