<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves every file the app now serves through the access-checked
 * /files/{path} route (see App\Support\ProtectedFiles) off the 'public'
 * disk, where the web server handed it to anyone with the URL, onto the
 * private 'local' disk. Paths in the database do not change -- only the
 * disk they live on -- so no row is rewritten.
 *
 * Safe to re-run: a file already on the private disk is left alone. A file
 * referenced in the database but missing from both disks is logged, not
 * fatal (it was already a broken link before this migration).
 */
return new class extends Migration
{
    private const SOURCES = [
        ['materials', 'path'],
        ['material_versions', 'path'],
        ['activities', 'path'],
        ['assignment_submissions', 'file_path'],
        ['course_sessions', 'recording_path'],
    ];

    public function up(): void
    {
        $this->move(Storage::disk('public'), Storage::disk('local'));
    }

    public function down(): void
    {
        $this->move(Storage::disk('local'), Storage::disk('public'));
    }

    private function move($from, $to): void
    {
        $paths = collect(self::SOURCES)
            ->flatMap(fn ($source) => DB::table($source[0])->whereNotNull($source[1])->where($source[1], '!=', '')->pluck($source[1]))
            ->unique();

        foreach ($paths as $path) {
            if ($to->exists($path)) {
                // Same hashed name on both sides is the same upload; drop the exposed copy.
                if ($from->exists($path) && $from->size($path) === $to->size($path)) {
                    $from->delete($path);
                }

                continue;
            }
            if (! $from->exists($path)) {
                Log::warning('Course file referenced in the database is missing on disk', ['path' => $path]);

                continue;
            }
            $stream = $from->readStream($path);
            $to->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $from->delete($path);
        }
    }
};
