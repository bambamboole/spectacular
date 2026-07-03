<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

final class MalformedPayloadWebhook
{
    /**
     * @return array{int}
     */
    public function webhookPayload(): array
    {
        return [123];
    }
}
