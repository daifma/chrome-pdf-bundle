<?php

namespace Daif\ChromePdfBundle\Enumeration;

interface PaperSizeInterface
{
    public function width(): float;

    public function height(): float;

    public function unit(): Unit;
}
