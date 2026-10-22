<?php

namespace App\Http\Controllers;

use App\Support\ProtectedFiles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * The only way to read a file on the private disk (see ProtectedFiles).
 * BinaryFileResponse answers HTTP Range requests, so video seeking keeps
 * working exactly as it did when the web server served these directly.
 */
class FileController extends Controller
{
    /** Types the browser may show inline (iframe / <video>); everything else downloads. */
    private const INLINE_PREFIXES = ['application/pdf', 'video/', 'audio/', 'image/png', 'image/jpeg', 'image/gif', 'text/plain'];

    public function show(string $path): BinaryFileResponse
    {
        abort_if($path === '' || str_contains($path, '..') || str_contains($path, "\0"), 404);
        // 404 rather than 403 for someone without access, so the response
        // does not confirm that a given file name exists.
        abort_unless(ProtectedFiles::allows(Auth::user(), $path), 404);

        $disk = Storage::disk(ProtectedFiles::DISK);
        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $inline = collect(self::INLINE_PREFIXES)->contains(fn ($prefix) => str_starts_with($mime, $prefix));

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ])->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($path),
        );
    }
}
