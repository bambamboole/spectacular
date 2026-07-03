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
        $json = json_encode(
            $document,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($this->isPretty() ? JSON_PRETTY_PRINT : 0),
        ).PHP_EOL;

        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json);

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
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
