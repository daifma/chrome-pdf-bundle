<?php

namespace Daif\ChromePdfBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ChromePdfExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('chrome_pdf_asset', [ChromePdfRuntime::class, 'getAssetUrl']),
            new TwigFunction('chrome_pdf_font_style_tag', [ChromePdfRuntime::class, 'getFontStyleTag'], ['is_safe' => ['html']]),
            new TwigFunction('chrome_pdf_font_face', [ChromePdfRuntime::class, 'getFontFace'], ['is_safe' => ['css']]),
        ];
    }
}
