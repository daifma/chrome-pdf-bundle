<?php

namespace Daif\ChromePdfBundle\Tests\DependencyInjection\CompilerPass;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\ChromePdf;
use Daif\ChromePdfBundle\ChromeScreenshot;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Daif\ChromePdfBundle\DependencyInjection\CompilerPass\ChromePdfPass;
use Daif\ChromePdfBundle\DependencyInjection\DaifChromePdfExtension;
use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Messenger\AsyncGenerator;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ChromePdfPassTest extends TestCase
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

    public function testItRegistersLocatorsEvenWhenATypeHasNoBuilder(): void
    {
        $container = $this->getContainerBuilder();
        $container->registerExtension($this->getExtension());
        $container->setDefinition('daif_chrome_pdf.pdf', (new Definition(ChromePdf::class))->setArguments([null]));
        $container->setDefinition('daif_chrome_pdf.screenshot', (new Definition(ChromeScreenshot::class))->setArguments([null]));

        (new ChromePdfPass())->process($container);

        $pdfLocator = $container->getDefinition('daif_chrome_pdf.pdf')->getArgument(0);
        $screenshotLocator = $container->getDefinition('daif_chrome_pdf.screenshot')->getArgument(0);

        self::assertInstanceOf(Reference::class, $pdfLocator);
        self::assertInstanceOf(Reference::class, $screenshotLocator);
        self::assertSame(['.daif_chrome_pdf.pdf_builder.html'], array_keys($this->getLocatorServices($container, $pdfLocator)));
        self::assertSame([], $this->getLocatorServices($container, $screenshotLocator));
    }

    public function testItDecoratesBuildersWhenDataCollectorIsRegistered(): void
    {
        $container = $this->getContainerBuilder(true);
        $container->registerExtension($this->getExtension());

        (new ChromePdfPass())->process($container);

        self::assertTrue($container->hasDefinition('.debug.daif_chrome_pdf.pdf_builder.html'));
        $decorator = $container->getDefinition('.debug.daif_chrome_pdf.pdf_builder.html');
        self::assertSame(TraceableBuilder::class, $decorator->getClass());
        self::assertSame(['.daif_chrome_pdf.pdf_builder.html', null, 0], $decorator->getDecoratedService());
        self::assertFalse($decorator->isShared());
        self::assertFalse($container->getDefinition('.daif_chrome_pdf.pdf_builder.html')->isShared());
    }

    public function testItGivesTheAsyncHandlerABuilderLocatorKeyedByClass(): void
    {
        $container = $this->getContainerBuilder();
        $container->registerExtension($this->getExtension());
        $this->registerAsyncServices($container);
        $container->setDefinition('messenger.default_bus', new Definition(\stdClass::class));
        $container->setDefinition('http_client', new Definition(\stdClass::class));

        (new ChromePdfPass())->process($container);

        $locator = $container->getDefinition('daif_chrome_pdf.messenger.generate_document_handler')->getArgument('$builders');
        self::assertInstanceOf(Reference::class, $locator);
        self::assertSame([self::BUILDER], array_keys($this->getLocatorServices($container, $locator)));
        self::assertTrue($container->hasDefinition('daif_chrome_pdf.async_generator'));
    }

    public function testItFailsWhenAsyncIsEnabledWithoutMessenger(): void
    {
        $container = $this->getContainerBuilder();
        $container->registerExtension($this->getExtension());
        $this->registerAsyncServices($container);
        $container->setDefinition('http_client', new Definition(\stdClass::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires the "messenger.default_bus" service');

        (new ChromePdfPass())->process($container);
    }

    private function registerAsyncServices(ContainerBuilder $container): void
    {
        $container->setDefinition('daif_chrome_pdf.async_generator', (new Definition(AsyncGenerator::class))->setArguments(['$bus' => new Reference('messenger.default_bus')]));
        $container->setDefinition('daif_chrome_pdf.messenger.generate_document_handler', (new Definition(GenerateDocumentHandler::class))->setArguments(['$builders' => null]));
    }

    /**
     * @return array<string, mixed>
     */
    private function getLocatorServices(ContainerBuilder $container, Reference $reference): array
    {
        $locator = $container->getDefinition((string) $reference);
        $factory = $locator->getFactory();
        if (\is_array($factory) && $factory[0] instanceof Reference) {
            $locator = $container->getDefinition((string) $factory[0]);
        }

        $services = $locator->getArgument(0);
        self::assertIsArray($services);

        return $services;
    }
}
