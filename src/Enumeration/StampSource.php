<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum StampSource: string
{
    case Text = 'text';
    case Image = 'image';
    case Pdf = 'pdf';
}
