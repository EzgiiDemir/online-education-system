<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookSubscription;

/**
 * The single place an outbound event notification gets fanned out to
 * subscribers -- called from CompletionEngine (enrollment.completed,
 * certificate.issued), EnrollmentService (enrollment.created), and
 * Certificate::revoke() (certificate.revoked), the same "one choke point
 * per concern" shape as CompletionEngine/EnrollmentService themselves.
 */
class WebhookDispatcher
{
    public function dispatch(string $event, array $payload): void
    {
        WebhookSubscription::where('is_active', true)->get()
            ->filter(fn (WebhookSubscription $subscription) => $subscription->subscribesTo($event))
            ->each(fn (WebhookSubscription $subscription) => DeliverWebhookJob::dispatch($subscription, $event, $payload));
    }
}
