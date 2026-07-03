<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

final readonly class NullWebhookSubscriptionRepository implements WebhookSubscriptionRepository
{
    public function forEvent(string $eventName, object $event): iterable
    {
        return [];
    }
}
