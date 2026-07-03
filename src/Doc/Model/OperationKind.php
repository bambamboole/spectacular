<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

enum OperationKind: string
{
    case Http = 'http';
    case Message = 'message';
}
