<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

trait ChromiumPdfTrait
{
    use Chromium\AssetTrait;
    use Chromium\ContentTrait;
    use Chromium\CookieTrait;
    use Chromium\CustomHttpHeadersTrait;
    use Chromium\EmulatedMediaTypeTrait;

    use Chromium\PdfPagePropertiesTrait;
    use Chromium\PerformanceModeTrait;
    use Chromium\WaitBeforeRenderingTrait;
}
