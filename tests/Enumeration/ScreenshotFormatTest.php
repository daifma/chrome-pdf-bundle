<?php

namespace Daif\ChromePdfBundle\Tests\Enumeration;

use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;
use PHPUnit\Framework\TestCase;

final class ScreenshotFormatTest extends TestCase
{
    public function testMimeTypesAndExtensions(): void
    {
        self::assertSame(['image/png', 'image/jpeg', 'image/webp'], array_map(static fn (ScreenshotFormat $f): string => $f->mimeType(), ScreenshotFormat::cases()));
        self::assertSame(['png', 'jpg', 'webp'], array_map(static fn (ScreenshotFormat $f): string => $f->extension(), ScreenshotFormat::cases()));
    }
}
