<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Tests\ProviderOrderTestCase;
use Bambamboole\Spectacular\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(ProviderOrderTestCase::class)->in('Integration');
