<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum PageLayout: int
{
    case None = 0;
    case SinglePage = 1;
    case OneColumn = 2;
    case TwoColumns = 3;
}
