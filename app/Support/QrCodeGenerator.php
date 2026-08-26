<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as ChillerlanQrCode;
use chillerlan\QRCode\QROptions;

/**
 * Thin wrapper around chillerlan/php-qrcode, scoped to what this app
 * actually needs: a QR code for a certificate PDF. SVG (not PNG) is
 * deliberate, not a preference -- this environment has neither ext-gd nor
 * ext-imagick installed, so raster output isn't available at all; SVG is
 * the one output mode the library can produce without either.
 *
 * DomPDF's CPDF adapter has no frame/renderer for a bare inline <svg>
 * element at all (verified directly against its source: the only "svg"
 * case in Adapter/CPDF.php is inside the <img> drawing switch, calling
 * addSvgFromFile() -- it's the image pathway, not a generic vector-content
 * one). An inline <svg>...</svg> written straight into the HTML body is
 * silently skipped during layout, with no warning or exception -- it just
 * renders nothing. The only way that reaches the page is through that same
 * <img> pathway: base64-encode the SVG as a data: URI and embed it via a
 * real <img src="..."> tag, exactly as if it were any other image file.
 *
 * Named QrCodeGenerator rather than QrCode: PHP class names are
 * case-insensitive, so a class QrCode in this file would collide with the
 * imported chillerlan\QRCode\QRCode symbol.
 */
class QrCodeGenerator
{
    /**
     * Returns a bare <svg width="$size" height="$size">...</svg> string.
     * The library's own output only sets a viewBox, no width/height --
     * fine for a browser (it falls back to sane defaults) but DomPDF needs
     * an explicit pixel size on the <svg> tag itself or it renders the
     * code at an oversized default, pushing a one-page certificate onto
     * three pages. Not directly embeddable in a DomPDF-rendered view (see
     * class docblock) -- use dataUri() for that; this is kept as the raw
     * primitive for anywhere that DOES support inline SVG (a browser page)
     * and is covered directly by QrCodeGeneratorTest.
     */
    public static function svg(string $data, int $size = 100): string
    {
        $options = new QROptions;
        $options->outputType = QROutputInterface::MARKUP_SVG;
        $options->outputBase64 = false;
        $options->svgAddXmlHeader = false;

        $svg = (new ChillerlanQrCode($options))->render($data);

        return preg_replace('/<svg /', '<svg width="'.$size.'" height="'.$size.'" ', $svg, 1);
    }

    /**
     * A data: URI wrapping the same SVG, for embedding via a real <img>
     * tag -- the only QR embedding that actually renders under DomPDF.
     */
    public static function dataUri(string $data, int $size = 100): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(static::svg($data, $size));
    }
}
