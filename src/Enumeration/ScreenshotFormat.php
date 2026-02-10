<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum ScreenshotFormat: string
{
    case Png = 'png';
    case Jpeg = 'jpeg';
    case Webp = 'webp';
}
