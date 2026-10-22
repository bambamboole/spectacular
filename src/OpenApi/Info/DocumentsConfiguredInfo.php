<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Info;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

/**
 * Fills the document's info object from configuration, keeping what Scramble already
 * resolved for anything the app leaves unset.
 */
final readonly class DocumentsConfiguredInfo implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        if (InfoConfig::values() === []) {
            return;
        }

        $info = $document->info;
        $info->title = InfoConfig::string('title') ?? $info->title;
        $info->version = InfoConfig::string('version') ?? $info->version;
        $info->description = InfoConfig::string('description') ?? $info->description;
        $info->termsOfService = InfoConfig::string('terms_of_service') ?? $info->termsOfService;
        $info->contact = InfoConfig::contact() ?? $info->contact;
        $info->license = InfoConfig::license() ?? $info->license;
    }
}
