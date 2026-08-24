<?php

namespace Tests\Unit;

use App\Support\QrCodeGenerator;
use PHPUnit\Framework\TestCase;

class QrCodeGeneratorTest extends TestCase
{
    public function test_it_returns_a_sized_inline_svg(): void
    {
        $svg = QrCodeGenerator::svg('https://example.com/verify/CERT-1');

        $this->assertStringStartsWith('<svg width="100" height="100"', $svg);
        $this->assertStringEndsWith('</svg>', trim($svg));
        $this->assertStringNotContainsString('<?xml', $svg, 'the XML prolog must be stripped for inline HTML embedding');
    }

    public function test_the_size_is_configurable(): void
    {
        $svg = QrCodeGenerator::svg('https://example.com/verify/CERT-1', 200);

        $this->assertStringStartsWith('<svg width="200" height="200"', $svg);
    }
}
