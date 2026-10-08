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

    public function test_svg_size_can_use_millimeter_units_for_pdf(): void
    {
        $svg = QrCodeGenerator::svg('https://example.com/verify/CERT-1', 53, 'mm');

        $this->assertStringStartsWith('<svg width="53mm" height="53mm"', $svg);
    }

    /**
     * dataUri() exists because DomPDF cannot render a bare inline <svg> at
     * all (see the class docblock and CertificateLifecycleTest) -- an <img>
     * tag pointing at this data: URI is the only pathway that actually
     * renders under DomPDF.
     */
    public function test_data_uri_wraps_the_same_svg_as_base64(): void
    {
        $svg = QrCodeGenerator::svg('https://example.com/verify/CERT-1');
        $uri = QrCodeGenerator::dataUri('https://example.com/verify/CERT-1');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertSame($svg, base64_decode(substr($uri, strlen('data:image/svg+xml;base64,'))));
    }
}
