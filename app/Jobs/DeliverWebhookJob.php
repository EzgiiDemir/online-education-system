<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * One queued job per (subscription, event) delivery -- signs the payload
 * with the subscription's secret (HMAC-SHA256, in X-Webhook-Signature) so
 * the receiver can verify it actually came from here, logs every attempt
 * to webhook_deliveries regardless of outcome, and throws on a
 * failed/unreachable attempt so Laravel's own queue retry (tries/backoff
 * below) drives re-delivery instead of a hand-rolled retry loop.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public WebhookSubscription $subscription,
        public string $event,
        public array $payload,
        // True only for the admin panel's "Test gönder" button: bypasses
        // the events-subscribed-to check (a test ping isn't one of the
        // real event names) and never throws/retries -- there's no queue
        // retry policy that makes sense for a one-off manual test the
        // admin is watching the result of synchronously.
        public bool $isTest = false,
    ) {}

    public function backoff(): array
    {
        return [30, 300, 1800, 3600];
    }

    public function handle(): void
    {
        // Re-checked at execution time, not just at dispatch time -- an
        // admin may have deactivated or unsubscribed this URL in the
        // window between the triggering event and this job actually
        // running, especially on a retried attempt minutes later.
        if (! $this->isTest && ! $this->subscription->subscribesTo($this->event)) {
            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'data' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ]);
        $signature = hash_hmac('sha256', $body, $this->subscription->secret);

        $response = null;
        $errorMessage = null;
        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'X-Webhook-Event' => $this->event,
                    'X-Webhook-Signature' => $signature,
                ])
                ->timeout(10)
                ->post($this->subscription->url);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        $successful = $response?->successful() ?? false;

        WebhookDelivery::create([
            'webhook_subscription_id' => $this->subscription->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'attempt' => $this->attempts(),
            'response_status' => $response?->status(),
            'response_body' => $response ? Str::limit($response->body(), 2000) : $errorMessage,
            'successful' => $successful,
            'delivered_at' => $successful ? now() : null,
        ]);

        if (! $successful && ! $this->isTest) {
            throw new \RuntimeException('Webhook delivery failed: '.($errorMessage ?? "HTTP {$response->status()}"));
        }
    }
}
