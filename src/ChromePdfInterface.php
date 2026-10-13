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

    /**
     * @return LibreOfficePdfBuilder
     */
    public function office(): BuilderInterface;

    /**
     * @return MergePdfBuilder
     */
    public function merge(): BuilderInterface;

    /**
     * @return ConvertPdfBuilder
     */
    public function convert(): BuilderInterface;

    /**
     * @return SplitPdfBuilder
     */
    public function split(): BuilderInterface;

    /**
     * @return FlattenPdfBuilder
     */
    public function flatten(): BuilderInterface;

    /**
     * @return EncryptPdfBuilder
     */
    public function encrypt(): BuilderInterface;

    /**
     * @return EmbedPdfBuilder
     */
    public function embed(): BuilderInterface;

    /**
     * @return StampPdfBuilder
     */
    public function stamp(): BuilderInterface;

    /**
     * @return WatermarkPdfBuilder
     */
    public function watermark(): BuilderInterface;

    /**
     * @return RotatePdfBuilder
     */
    public function rotate(): BuilderInterface;
}
