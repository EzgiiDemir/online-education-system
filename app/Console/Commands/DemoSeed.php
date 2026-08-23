<?php

namespace App\Console\Commands;

use Database\Seeders\DemoFakeDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Thin wrapper around DemoFakeDataSeeder so the showcase data can be added
 * with a short, memorable command instead of `db:seed --class=...`. See
 * DemoClear for the matching removal command.
 */
#[Signature('demo:seed')]
#[Description('Add static fake showcase data (learners, courses, enrollments) for demo purposes')]
class DemoSeed extends Command
{
    public function handle(): int
    {
        $this->call('db:seed', ['--class' => DemoFakeDataSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
