<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Security;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

/**
 * Offers every configured authentication mode on the document. Each mode becomes
 * its own document-level requirement, which OpenAPI reads as alternatives — a
 * client picks one.
 */
final readonly class DocumentsConfiguredSecurity implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach (SecurityConfig::schemes() as $name => $config) {
            $document->secure(SecuritySchemeFactory::make($name, $config));
        }
    }
}
