<?php

namespace Daif\ChromePdfBundle\Tests\DependencyInjection\CompilerPass;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\DependencyInjection\CompilerPass\ChromePdfPass;
use Daif\ChromePdfBundle\DependencyInjection\DaifChromePdfExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ChromePdfPassTest extends TestCase
{
    private const BUILDER = HtmlPdfBuilder::class;

    private function getContainerBuilder(bool $withDataCollector = false): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if (true === $withDataCollector) {
            $dataCollector = new Definition();

            $container->setDefinition('daif_chrome_pdf.data_collector', $dataCollector);
        }

        $htmlPdfBuilderService = new Definition(self::BUILDER);
        $htmlPdfBuilderService->addTag('daif_chrome_pdf.builder');
        $container->setDefinition('.daif_chrome_pdf.pdf_builder.html', $htmlPdfBuilderService);

        $someRandomService = new Definition(\stdClass::class);
        $container->setDefinition('.service.random', $someRandomService);

        return $container;
    }

    private function getExtension(): DaifChromePdfExtension
    {
        $extension = new DaifChromePdfExtension();
        $extension->registerBuilder(self::BUILDER);

        return $extension;
    }

    public function testItDoesNothingIfDataCollectorNotRegistered(): void
    {
        $container = $this->getContainerBuilder();
        $container->registerExtension($this->getExtension());

        $serviceIds = $container->getServiceIds();

        self::assertNotContains('daif_chrome_pdf.data_collector', $serviceIds);

        $compilerPass = new ChromePdfPass();
        $compilerPass->process($container);

        self::assertSame($serviceIds, $container->getServiceIds());
    }
}
