<?php

namespace Daif\ChromePdfBundle;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;

interface ChromePdfInterface
{
    /**
     * @template T of BuilderInterface
     *
     * @param string|class-string<T> $builder
     *
     * @return ($builder is class-string ? T : BuilderInterface)
     */
    public function get(string $builder): BuilderInterface;

    /**
     * @return HtmlPdfBuilder
     */
    public function html(): BuilderInterface;

    /**
     * @return UrlPdfBuilder
     */
    public function url(): BuilderInterface;

    /**
     * @return MarkdownPdfBuilder
     */
    public function markdown(): BuilderInterface;
}
