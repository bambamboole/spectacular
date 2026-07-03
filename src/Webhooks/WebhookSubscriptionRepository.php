<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

interface WebhookSubscriptionRepository
{
    /**
     * @return iterable<WebhookSubscription>
     */
    public function forEvent(string $eventName, object $event): iterable;
}
