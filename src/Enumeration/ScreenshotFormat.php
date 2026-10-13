<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum ScreenshotFormat: string
{
    case Png = 'png';
    case Jpeg = 'jpeg';
    case Webp = 'webp';

    public function mimeType(): string
    {
        return match ($this) {
            self::Png => 'image/png',
            self::Jpeg => 'image/jpeg',
            self::Webp => 'image/webp',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Png => 'png',
            self::Jpeg => 'jpg',
            self::Webp => 'webp',
        };
    }
}
