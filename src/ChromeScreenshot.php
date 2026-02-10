<?php

namespace Daif\ChromePdfBundle;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Psr\Container\ContainerInterface;

final class ChromeScreenshot implements ChromeScreenshotInterface
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
     *   $key is 'html' ? HtmlScreenshotBuilder :
     *   $key is 'url' ? UrlScreenshotBuilder :
     *   $key is 'markdown' ? MarkdownScreenshotBuilder :
     *   BuilderInterface
     * )
     */
    private function getInternal(string $key): BuilderInterface
    {
        return $this->get(".daif_chrome_pdf.screenshot_builder.{$key}");
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
