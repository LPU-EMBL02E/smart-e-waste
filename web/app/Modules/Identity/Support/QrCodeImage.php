<?php

namespace App\Modules\Identity\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Draws a user's QR token as an inline SVG, on the server, so the profile page
 * needs no script. API_Design.md §7.3, P32.
 *
 * Black on white with the standard four-module quiet zone, which the page must
 * leave alone (web/docs/DESIGN.md §7). The only class that knows the package.
 */
final class QrCodeImage
{
    /** The SVG's own size; the page scales it. */
    private const int SIZE_PX = 240;

    private const int QUIET_ZONE_MODULES = 4;

    public static function svg(string $token): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::SIZE_PX, self::QUIET_ZONE_MODULES),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($token, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());

        // The package starts with an XML declaration, which isn't valid inside HTML.
        return substr($svg, strpos($svg, '<svg'));
    }
}
