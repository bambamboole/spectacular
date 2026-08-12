<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\Filters;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
}
