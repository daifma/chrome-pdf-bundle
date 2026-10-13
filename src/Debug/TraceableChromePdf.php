<?php

namespace Daif\ChromePdfBundle\Debug;

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
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Symfony\Contracts\Service\ResetInterface;

final class TraceableChromePdf implements ChromePdfInterface, ResetInterface
{
    /**
     * @var list<array{string, TraceableBuilder}>
     */
    private array $builders = [];

    public function __construct(
        private readonly ChromePdfInterface $inner,
    ) {
    }

    public function get(string $builder): BuilderInterface
    {
        $traceableBuilder = $this->inner->get($builder);

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = [$builder, $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return HtmlPdfBuilder|TraceableBuilder
     */
    public function html(): BuilderInterface
    {
        /** @var HtmlPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->html();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['html', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return UrlPdfBuilder|TraceableBuilder
     */
    public function url(): BuilderInterface
    {
        /** @var UrlPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->url();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['url', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return MarkdownPdfBuilder|TraceableBuilder
     */
    public function markdown(): BuilderInterface
    {
        /** @var MarkdownPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->markdown();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['markdown', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return LibreOfficePdfBuilder|TraceableBuilder
     */
    public function office(): BuilderInterface
    {
        /** @var LibreOfficePdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->office();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['office', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return MergePdfBuilder|TraceableBuilder
     */
    public function merge(): BuilderInterface
    {
        /** @var MergePdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->merge();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['merge', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return ConvertPdfBuilder|TraceableBuilder
     */
    public function convert(): BuilderInterface
    {
        /** @var ConvertPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->convert();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['convert', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return SplitPdfBuilder|TraceableBuilder
     */
    public function split(): BuilderInterface
    {
        /** @var SplitPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->split();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['split', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return FlattenPdfBuilder|TraceableBuilder
     */
    public function flatten(): BuilderInterface
    {
        /** @var FlattenPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->flatten();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['flatten', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return EncryptPdfBuilder|TraceableBuilder
     */
    public function encrypt(): BuilderInterface
    {
        /** @var EncryptPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->encrypt();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['encrypt', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return EmbedPdfBuilder|TraceableBuilder
     */
    public function embed(): BuilderInterface
    {
        /** @var EmbedPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->embed();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['embed', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return StampPdfBuilder|TraceableBuilder
     */
    public function stamp(): BuilderInterface
    {
        /** @var StampPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->stamp();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['stamp', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return WatermarkPdfBuilder|TraceableBuilder
     */
    public function watermark(): BuilderInterface
    {
        /** @var WatermarkPdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->watermark();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['watermark', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return RotatePdfBuilder|TraceableBuilder
     */
    public function rotate(): BuilderInterface
    {
        /** @var RotatePdfBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->rotate();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['rotate', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return list<array{string, TraceableBuilder}>
     */
    public function getBuilders(): array
    {
        return $this->builders;
    }

    public function reset(): void
    {
        $this->builders = [];
    }
}
