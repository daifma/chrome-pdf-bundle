<?php

namespace Daif\ChromePdfBundle;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Daif\ChromePdfBundle\DependencyInjection\CompilerPass\ChromePdfPass;
use Daif\ChromePdfBundle\DependencyInjection\DaifChromePdfExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DaifChromePdfBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function createContainerExtension(): DaifChromePdfExtension
    {
        $extension = new DaifChromePdfExtension();

        $extension->registerBuilder(HtmlPdfBuilder::class);
        $extension->registerBuilder(MarkdownPdfBuilder::class);
        $extension->registerBuilder(UrlPdfBuilder::class);

        $extension->registerBuilder(HtmlScreenshotBuilder::class);
        $extension->registerBuilder(MarkdownScreenshotBuilder::class);
        $extension->registerBuilder(UrlScreenshotBuilder::class);

        return $extension;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ChromePdfPass());
    }
}
