<?php

namespace Daif\ChromePdfBundle\Enumeration;

enum Part: string
{
    case Header = 'header.html';
    case Body = 'index.html';
    case Footer = 'footer.html';
}
