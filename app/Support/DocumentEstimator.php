<?php

namespace App\Support;

class DocumentEstimator
{
    private const MINUTES_PER_UNIT = 1.5;

    /**
     * Rough "estimated reading time" for a document, shown to the learner as
     * a hint only — it never gates completion. Returns null when the file
     * type isn't one we know how to estimate (the UI just omits the hint).
     */
    public static function estimateMinutes(string $absolutePath, ?string $mime): ?int
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $units = match (true) {
            $mime === 'application/pdf' => self::countPdfPages($absolutePath),
            $mime === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => self::countPptxSlides($absolutePath),
            default => null,
        };

        return $units ? max(1, (int) round($units * self::MINUTES_PER_UNIT)) : null;
    }

    /**
     * Counts "/Type /Page" object markers in the raw PDF bytes. This is a
     * heuristic, not a real PDF parse (no library for that is installed) —
     * it works for the vast majority of standard, non-scanned PDFs but can
     * under/over-count on unusual or malformed files. Fine for a "roughly
     * this long" hint; not something to gate anything on.
     */
    private static function countPdfPages(string $path): ?int
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return null;
        }

        $count = preg_match_all('/\/Type\s*\/Page(?!s)/', $bytes);

        return $count > 0 ? $count : null;
    }

    /**
     * A .pptx is a zip archive; each slide is its own ppt/slides/slideN.xml
     * part, so the slide count is just that file count.
     */
    private static function countPptxSlides(string $path): ?int
    {
        try {
            $archive = new \PharData($path);
        } catch (\Exception) {
            return null;
        }

        $count = 0;
        foreach (new \RecursiveIteratorIterator($archive) as $entry) {
            if (preg_match('#/ppt/slides/slide\d+\.xml$#', $entry->getPathname())) {
                $count++;
            }
        }

        return $count > 0 ? $count : null;
    }
}
