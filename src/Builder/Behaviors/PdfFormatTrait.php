<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Enumeration\PdfFormat;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;

/**
 * Requires LibreOffice.
 *
 * @package Behavior\\PdfFormat
 */
trait PdfFormatTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Convert the resulting PDF into the given PDF/A format.
     *
     * @example pdfFormat(PdfFormat::Pdf1b)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('pdf_format', enumClass: PdfFormat::class))]
    public function pdfFormat(PdfFormat|null $format): static
    {
        if (!$format) {
            $this->getBodyBag()->unset('pdfa');
        } else {
            $this->getBodyBag()->set('pdfa', $format);
        }

        return $this;
    }

    /**
     * Enable PDF for Universal Access for optimal accessibility.
     *
     * @example pdfUniversalAccess()  // is same as `->pdfUniversalAccess(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('pdf_universal_access'))]
    public function pdfUniversalAccess(bool $bool = true): static
    {
        $this->getBodyBag()->set('pdfua', $bool);

        return $this;
    }
}
