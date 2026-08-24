<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'successful' => 'boolean', 'delivered_at' => 'datetime'];

    public function subscription()
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
