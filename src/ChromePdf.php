<?php

namespace Daif\ChromePdfBundle;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
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
     * @param 'html'|'url'|'markdown' $key
     *
     * @return (
     *   $key is 'html' ? HtmlPdfBuilder :
     *   $key is 'url' ? UrlPdfBuilder :
     *   $key is 'markdown' ? MarkdownPdfBuilder :
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
}
