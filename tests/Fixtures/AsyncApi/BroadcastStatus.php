<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

enum BroadcastStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
}
