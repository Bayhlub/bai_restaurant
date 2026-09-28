<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /**
     * Render the given text as an inline SVG string (no XML prolog).
     */
    public static function svg(string $text, int $size = 200): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);

        $svg = (new Writer($renderer))->writeString($text);

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
