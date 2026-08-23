<?php

namespace App\Support;

use App\Models\DemoSeedRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Marks a model row as "fake showcase data" so it can be found and deleted
 * later in one pass (see App\Console\Commands\ClearDemoFakeData). Call this
 * for every row DemoFakeDataSeeder creates, right after creating it.
 */
class DemoSeedTracker
{
    public static function track(Model $model): void
    {
        DemoSeedRecord::firstOrCreate([
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ], ['created_at' => now()]);
    }
}
