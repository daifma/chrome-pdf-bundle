<?php

namespace Daif\ChromePdfBundle\Debug;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;

final class TraceableChromePdf implements ChromePdfInterface
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
     * @return list<array{string, TraceableBuilder}>
     */
    public function getBuilders(): array
    {
        return $this->builders;
    }
}
