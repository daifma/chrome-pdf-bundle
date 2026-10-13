<?php

namespace Daif\ChromePdfBundle;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Pdf\ConvertPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EmbedPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EncryptPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\FlattenPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\LibreOfficePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\RotatePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\SplitPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\StampPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\WatermarkPdfBuilder;
use Psr\Container\ContainerInterface;

final class ChromePdf implements ChromePdfInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function get(string $builder): BuilderInterface
    {
        return $this->container->get($builder);
    }

    /**
     * @param 'html'|'url'|'markdown'|'office'|'merge'|'convert'|'split'|'flatten'|'encrypt'|'embed'|'stamp'|'watermark'|'rotate' $key
     *
     * @return (
     *   $key is 'html' ? HtmlPdfBuilder :
     *   $key is 'url' ? UrlPdfBuilder :
     *   $key is 'markdown' ? MarkdownPdfBuilder :
     *   $key is 'office' ? LibreOfficePdfBuilder :
     *   $key is 'merge' ? MergePdfBuilder :
     *   $key is 'convert' ? ConvertPdfBuilder :
     *   $key is 'split' ? SplitPdfBuilder :
     *   $key is 'flatten' ? FlattenPdfBuilder :
     *   $key is 'encrypt' ? EncryptPdfBuilder :
     *   $key is 'embed' ? EmbedPdfBuilder :
     *   $key is 'stamp' ? StampPdfBuilder :
     *   $key is 'watermark' ? WatermarkPdfBuilder :
     *   $key is 'rotate' ? RotatePdfBuilder :
     *   BuilderInterface
     * )
     */
    private function getInternal(string $key): BuilderInterface
    {
        return $this->get(".daif_chrome_pdf.pdf_builder.{$key}");
    }

    public function html(): BuilderInterface
    {
        return $this->getInternal('html');
    }

    public function url(): BuilderInterface
    {
        return $this->getInternal('url');
    }

    public function markdown(): BuilderInterface
    {
        return $this->getInternal('markdown');
    }

    public function office(): BuilderInterface
    {
        return $this->getInternal('office');
    }

    public function merge(): BuilderInterface
    {
        return $this->getInternal('merge');
    }

    public function convert(): BuilderInterface
    {
        return $this->getInternal('convert');
    }

    public function split(): BuilderInterface
    {
        return $this->getInternal('split');
    }

    public function flatten(): BuilderInterface
    {
        return $this->getInternal('flatten');
    }

    public function encrypt(): BuilderInterface
    {
        return $this->getInternal('encrypt');
    }

    public function embed(): BuilderInterface
    {
        return $this->getInternal('embed');
    }

    public function stamp(): BuilderInterface
    {
        return $this->getInternal('stamp');
    }

    public function watermark(): BuilderInterface
    {
        return $this->getInternal('watermark');
    }

    public function rotate(): BuilderInterface
    {
        return $this->getInternal('rotate');
    }
}
