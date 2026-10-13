<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum WatermarkSource: string
{
    case Text = 'text';
    case Image = 'image';
    case Pdf = 'pdf';
}
