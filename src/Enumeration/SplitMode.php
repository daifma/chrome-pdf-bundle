<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum SplitMode: string
{
    case Intervals = 'intervals';
    case Pages = 'pages';
}
