<?php

namespace Daif\ChromePdfBundle\Debug;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Daif\ChromePdfBundle\ChromeScreenshotInterface;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;

final class TraceableChromePdfScreenshot implements ChromeScreenshotInterface
{
    /**
     * @var list<array{string, TraceableBuilder}>
     */
    private array $builders = [];

    public function __construct(
        private readonly ChromeScreenshotInterface $inner,
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
     * @return HtmlScreenshotBuilder|TraceableBuilder
     */
    public function html(): BuilderInterface
    {
        /** @var HtmlScreenshotBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->html();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['html', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return UrlScreenshotBuilder|TraceableBuilder
     */
    public function url(): BuilderInterface
    {
        /** @var UrlScreenshotBuilder|TraceableBuilder $traceableBuilder */
        $traceableBuilder = $this->inner->url();

        if (!$traceableBuilder instanceof TraceableBuilder) {
            return $traceableBuilder;
        }

        $this->builders[] = ['url', $traceableBuilder];

        return $traceableBuilder;
    }

    /**
     * @return MarkdownScreenshotBuilder|TraceableBuilder
     */
    public function markdown(): BuilderInterface
    {
        /** @var MarkdownScreenshotBuilder|TraceableBuilder $traceableBuilder */
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
