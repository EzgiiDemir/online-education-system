<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for a real bug found in resources/views/lms/home.blade.php:
 * Blade extracts raw @php...@endphp blocks with the regex
 * `/(?<!@)@php(.*?)@endphp/s` (Illuminate\View\Compilers\BladeCompiler::extractRawPhpBlocks).
 * It does not distinguish the self-contained `@php($expr)` shorthand from a real
 * `@php ... @endphp` block. If a file has a shorthand call BEFORE a real block,
 * the regex pairs the shorthand's `@php` with the block's `@endphp`, silently
 * swallowing everything in between (including directives like @auth/@guest) as
 * unprocessed raw text -- which then fails to compile with a ParseError.
 *
 * This scans every Blade view for that exact trap so it can never recur unnoticed.
 */
class BladePhpDirectiveSafetyTest extends TestCase
{
    public function test_no_blade_view_mixes_a_php_shorthand_call_before_a_php_block(): void
    {
        $offenders = [];
        $basePath = dirname(__DIR__, 2);

        foreach ($this->bladeFiles($basePath.'/resources/views') as $file) {
            $content = file_get_contents($file);
            preg_match_all('/@php(\s*\()?/', $content, $matches, PREG_OFFSET_CAPTURE);

            $firstShorthandPosition = null;
            $firstBlockPosition = null;

            foreach ($matches[0] as $i => $match) {
                $isShorthand = trim($matches[1][$i][0]) === '(';
                $position = $match[1];

                if ($isShorthand && $firstShorthandPosition === null) {
                    $firstShorthandPosition = $position;
                }
                if (! $isShorthand && $firstBlockPosition === null) {
                    $firstBlockPosition = $position;
                }
            }

            if ($firstShorthandPosition !== null && $firstBlockPosition !== null && $firstShorthandPosition < $firstBlockPosition) {
                $offenders[] = str_replace($basePath.DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertSame([], $offenders, "These Blade views mix a @php(...) shorthand call before a @php...@endphp block, which Blade will misparse:\n".implode("\n", $offenders));
    }

    private function bladeFiles(string $dir): array
    {
        $found = [];
        foreach (glob($dir.'/*') as $item) {
            if (is_dir($item)) {
                $found = array_merge($found, $this->bladeFiles($item));
            } elseif (str_ends_with($item, '.blade.php')) {
                $found[] = $item;
            }
        }

        return $found;
    }
}
