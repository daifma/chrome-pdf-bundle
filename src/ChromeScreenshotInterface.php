<?php

namespace Daif\ChromePdfBundle;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;

interface ChromeScreenshotInterface
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
     * @return HtmlScreenshotBuilder
     */
    public function html(): BuilderInterface;

    /**
     * @return UrlScreenshotBuilder
     */
    public function url(): BuilderInterface;

    /**
     * @return MarkdownScreenshotBuilder
     */
    public function markdown(): BuilderInterface;
}
