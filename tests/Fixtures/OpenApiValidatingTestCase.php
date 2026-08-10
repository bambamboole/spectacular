<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures;

use Bambamboole\Spectacular\OpenApi\Testing\ValidatesOpenApiSpec;
use Illuminate\Foundation\Testing\TestCase;

abstract class OpenApiValidatingTestCase extends TestCase
{
    use ValidatesOpenApiSpec;
}
