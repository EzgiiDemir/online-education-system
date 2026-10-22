<?php

namespace App\Console\Commands;

use App\Models\CourseSession;
use App\Support\ProtectedFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Run daily by the scheduler: deletes live-class recording files past
 * CourseSession::RECORDING_RETENTION_DAYS and clears the session's
 * recording fields. The session row itself, and everything else about it
 * (attendance, materials, chat), is untouched -- only the recording file
 * and its two columns are retention-limited.
 */
#[Signature('app:purge-expired-recordings')]
#[Description('Deletes live-class recordings past their retention window')]
class PurgeExpiredRecordings extends Command
{
    public function handle(): void
    {
        $sessions = CourseSession::whereNotNull('recording_path')
            ->where('recording_expires_at', '<', now())
            ->get();

        foreach ($sessions as $session) {
            Storage::disk(ProtectedFiles::DISK)->delete($session->recording_path);
            $session->update(['recording_path' => null, 'recording_expires_at' => null]);
        }

        $this->info("Silinen kayıtlar: {$sessions->count()}.");
    }
}
