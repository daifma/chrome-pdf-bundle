<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum InitialView: int
{
    case None = 0;
    case Outlines = 1;
    case Thumbnails = 2;
}
