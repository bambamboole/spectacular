<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\InvalidStateEndpoint;

use Bambamboole\Spectacular\Attributes\StateEndpoint;

#[StateEndpoint(path: 'not-a-state/{state}')]
final class NotAState {}
