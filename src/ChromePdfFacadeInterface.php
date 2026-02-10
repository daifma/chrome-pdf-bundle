<?php

namespace Daif\ChromePdfBundle;

interface ChromePdfFacadeInterface
{
    public function pdf(): ChromePdfInterface;

    public function screenshot(): ChromeScreenshotInterface;
}
