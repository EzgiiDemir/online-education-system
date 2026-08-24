<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as ChillerlanQrCode;
use chillerlan\QRCode\QROptions;

/**
 * Thin wrapper around chillerlan/php-qrcode, scoped to what this app
 * actually needs: an inline <svg> for a certificate PDF. SVG (not PNG) is
 * deliberate, not a preference -- this environment has neither ext-gd nor
 * ext-imagick installed, so raster output isn't available at all; SVG is
 * the one output mode the library can produce without either. DomPDF
 * renders an inline <svg> element directly rather than going through its
 * (patchier) data-URI image decoding path, so the XML prolog is stripped
 * and only the bare <svg>...</svg> markup is returned.
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
     * three pages.
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
}
