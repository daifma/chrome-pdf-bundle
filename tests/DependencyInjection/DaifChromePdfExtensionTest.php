<?php

namespace Daif\ChromePdfBundle\Tests\DependencyInjection;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Browser\ChromeBrowser;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Daif\ChromePdfBundle\DaifChromePdfBundle;
use Daif\ChromePdfBundle\DependencyInjection\DaifChromePdfExtension;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\LibreOffice;
use Daif\ChromePdfBundle\Engine\PdfPostProcessor;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Messenger\AsyncGenerator;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentHandler;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentMessage;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistry;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

final class DaifChromePdfExtensionTest extends TestCase
{
    private static function getContainerBuilder(bool $kernelDebug = false): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.debug' => $kernelDebug,
            'kernel.project_dir' => sys_get_temp_dir(),
        ]));
    }

    private static function getExtension(): DaifChromePdfExtension
    {
        $extension = (new DaifChromePdfBundle())->getContainerExtension();
        self::assertInstanceOf(DaifChromePdfExtension::class, $extension);

        return $extension;
    }

    public function testBrowserIsRegisteredWithDefaults(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);

        self::assertTrue($container->hasDefinition('daif_chrome_pdf.browser'));
        $definition = $container->getDefinition('daif_chrome_pdf.browser');

        self::assertSame(ChromeBrowser::class, $definition->getClass());
        self::assertTrue($definition->isShared());
        self::assertFalse($definition->hasTag('kernel.reset'), 'Resetting would kill Chrome after every request/message.');
        self::assertSame([['channel' => 'daif_chrome_pdf']], $definition->getTag('monolog.logger'));

        $arguments = $definition->getArguments();
        self::assertSame([
            '$chromeBinary',
            '$browserOptions',
            '$logger',
            '$timeout',
            '$noSandbox',
            '$ignoreCertificateErrors',
            '$tempDir',
            '$allowList',
            '$denyList',
            '$localFileRoots',
        ], array_keys($arguments));
        self::assertSame([], $arguments['$localFileRoots']);

        self::assertNull($arguments['$chromeBinary']);
        self::assertSame([], $arguments['$browserOptions']);
        self::assertInstanceOf(Reference::class, $arguments['$logger']);
        self::assertSame('logger', (string) $arguments['$logger']);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments['$logger']->getInvalidBehavior());
        self::assertSame(30000, $arguments['$timeout']);
        self::assertFalse($arguments['$noSandbox']);
        self::assertFalse($arguments['$ignoreCertificateErrors']);
        self::assertNull($arguments['$tempDir']);
        self::assertSame([], $arguments['$allowList']);
        self::assertSame([], $arguments['$denyList']);

        self::assertTrue($container->hasAlias(BrowserInterface::class));
        self::assertSame('daif_chrome_pdf.browser', (string) $container->getAlias(BrowserInterface::class));
    }

    public function testBrowserArgumentsFollowConfiguration(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([[
            'chrome_binary' => '/opt/chrome/chrome',
            'timeout' => 90000,
            'no_sandbox' => true,
            'ignore_certificate_errors' => true,
            'temp_dir' => '%kernel.project_dir%/var/tmp',
            'allow_list' => ['#^https://intranet\.#'],
            'deny_list' => ['#^https?://169\.254\.#', '#\.internal/#'],
            'browser_options' => ['windowSize' => [1280, 720]],
        ]], $container);

        $arguments = $container->getDefinition('daif_chrome_pdf.browser')->getArguments();

        self::assertSame('/opt/chrome/chrome', $arguments['$chromeBinary']);
        self::assertSame(['windowSize' => [1280, 720]], $arguments['$browserOptions']);
        self::assertSame(90000, $arguments['$timeout']);
        self::assertTrue($arguments['$noSandbox']);
        self::assertTrue($arguments['$ignoreCertificateErrors']);
        self::assertSame('%kernel.project_dir%/var/tmp', $arguments['$tempDir']);
        self::assertSame(['#^https://intranet\.#'], $arguments['$allowList']);
        self::assertSame(['#^https?://169\.254\.#', '#\.internal/#'], $arguments['$denyList']);
    }

    public function testNamedArgumentsMatchChromeBrowserConstructor(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);

        $constructor = (new \ReflectionClass(ChromeBrowser::class))->getConstructor();
        self::assertNotNull($constructor);
        $parameters = array_map(static fn (\ReflectionParameter $p): string => '$'.$p->getName(), $constructor->getParameters());

        foreach (array_keys($container->getDefinition('daif_chrome_pdf.browser')->getArguments()) as $name) {
            self::assertContains($name, $parameters, \sprintf('ChromeBrowser::__construct() has no "%s" parameter.', $name));
        }
    }

    public function testAssetsDirectoryAndRequestContext(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([[
            'assets_directory' => ['assets', 'templates'],
            'request_context' => ['base_uri' => 'https://example.com'],
        ]], $container);

        self::assertSame(['assets', 'templates'], $container->getDefinition('.daif_chrome_pdf.asset.base_dir_formatter')->getArgument(1));
        self::assertTrue($container->hasDefinition('.daif_chrome_pdf.request_context'));
        self::assertSame(['https://example.com'], $container->getDefinition('.daif_chrome_pdf.request_context')->getArguments());
    }

    public function testControllerListenerCanBeDisabled(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);
        self::assertTrue($container->hasDefinition('daif_chrome_pdf.http_kernel.stream_builder'));

        $container = self::getContainerBuilder();
        self::getExtension()->load([['controller_listener' => false]], $container);
        self::assertFalse($container->hasDefinition('daif_chrome_pdf.http_kernel.stream_builder'));
    }

    public function testDebugServicesOnlyInDebugMode(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);
        self::assertFalse($container->hasDefinition('daif_chrome_pdf.data_collector'));

        $container = self::getContainerBuilder(true);
        self::getExtension()->load([['default_options' => ['pdf' => ['html' => ['landscape' => true]]]]], $container);

        self::assertTrue($container->hasDefinition('daif_chrome_pdf.data_collector'));
        self::assertTrue($container->hasDefinition('daif_chrome_pdf.traceable_pdf'));
        self::assertTrue($container->hasDefinition('daif_chrome_pdf.traceable_screenshot'));

        $defaultOptions = $container->getDefinition('daif_chrome_pdf.data_collector')->getArgument(3);
        self::assertIsArray($defaultOptions);
        self::assertSame(['landscape' => true], $defaultOptions['pdf']['html']);
    }

    public function testBuilderConfiguratorReceivesDefaultOptionsPerBuilderClass(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([['default_options' => [
            'pdf' => ['html' => ['landscape' => true]],
            'screenshot' => ['url' => ['width' => 1024]],
        ]]], $container);

        $configurator = $container->getDefinition('daif_chrome_pdf.builder_configurator');

        $mapping = $configurator->getArgument(0);
        $values = $configurator->getArgument(1);
        self::assertIsArray($mapping);
        self::assertIsArray($values);

        // Every registered builder gets an entry, even without configured values.
        $extension = self::getExtension();
        foreach ([HtmlPdfBuilder::class, UrlScreenshotBuilder::class] as $class) {
            self::assertSame($extension->getBuilder($class), HtmlPdfBuilder::class === $class ? 'pdf' : 'screenshot');
            self::assertArrayHasKey($class, $mapping);
            self::assertArrayHasKey($class, $values);
        }

        self::assertSame(['landscape' => true], $values[HtmlPdfBuilder::class]);
        self::assertSame(['width' => 1024], $values[UrlScreenshotBuilder::class]);
        self::assertSame('landscape', $mapping[HtmlPdfBuilder::class]['landscape']['method']);
        self::assertSame('width', $mapping[UrlScreenshotBuilder::class]['width']['method']);
    }

    public function testEnginesAreRegisteredWithDefaults(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);

        foreach (['qpdf' => Qpdf::class, 'exiftool' => Exiftool::class, 'libreoffice' => LibreOffice::class] as $name => $class) {
            $id = 'daif_chrome_pdf.engine.'.$name;
            self::assertTrue($container->hasDefinition($id), $id);
            $definition = $container->getDefinition($id);
            self::assertSame($class, $definition->getClass());
            self::assertNull($definition->getArgument('$binary'));
            self::assertSame(120000, $definition->getArgument('$timeout'));
            self::assertNull($definition->getArgument('$tempDir'));
            self::assertSame($id, (string) $container->getAlias($class));
        }

        self::assertTrue($container->hasDefinition('daif_chrome_pdf.engine.post_processor'));
        self::assertSame(PdfPostProcessor::class, $container->getDefinition('daif_chrome_pdf.engine.post_processor')->getClass());
    }

    public function testEngineArgumentsFollowConfiguration(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([[
            'qpdf_binary' => '/opt/qpdf/bin/qpdf',
            'exiftool_binary' => '/usr/local/bin/exiftool',
            'libreoffice_binary' => '/opt/libreoffice/program/soffice',
            'engine_timeout' => 5000,
            'temp_dir' => '/var/tmp/pdf',
        ]], $container);

        self::assertSame('/opt/qpdf/bin/qpdf', $container->getDefinition('daif_chrome_pdf.engine.qpdf')->getArgument('$binary'));
        self::assertSame('/usr/local/bin/exiftool', $container->getDefinition('daif_chrome_pdf.engine.exiftool')->getArgument('$binary'));
        self::assertSame('/opt/libreoffice/program/soffice', $container->getDefinition('daif_chrome_pdf.engine.libreoffice')->getArgument('$binary'));
        self::assertSame(5000, $container->getDefinition('daif_chrome_pdf.engine.libreoffice')->getArgument('$timeout'));
        self::assertSame('/var/tmp/pdf', $container->getDefinition('daif_chrome_pdf.engine.qpdf')->getArgument('$tempDir'));
        self::assertSame('/var/tmp/pdf', $container->getDefinition('daif_chrome_pdf.engine.post_processor')->getArgument('$tempDir'));
    }

    public function testNamedArgumentsMatchEngineConstructors(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);

        foreach (['qpdf', 'exiftool', 'libreoffice', 'post_processor'] as $name) {
            $definition = $container->getDefinition('daif_chrome_pdf.engine.'.$name);
            $class = $definition->getClass();
            self::assertNotNull($class);
            self::assertTrue(class_exists($class));
            $constructor = (new \ReflectionClass($class))->getConstructor();
            self::assertNotNull($constructor);
            $parameters = array_map(static fn (\ReflectionParameter $p): string => '$'.$p->getName(), $constructor->getParameters());

            foreach (array_keys($definition->getArguments()) as $argument) {
                self::assertContains($argument, $parameters, \sprintf('%s::__construct() has no "%s" parameter.', $class, $argument));
            }
        }
    }

    public function testNamedWebhooksAreAddedToTheRegistry(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([[
            'webhook' => [
                'default' => [
                    'success' => ['url' => 'https://example.com/success'],
                    'error' => ['route' => 'webhook_error', 'method' => 'PATCH'],
                ],
            ],
        ]], $container);

        $registry = $container->getDefinition('.daif_chrome_pdf.webhook_configuration_registry');
        self::assertSame(WebhookConfigurationRegistry::class, $registry->getClass());
        self::assertSame('.daif_chrome_pdf.webhook_configuration_registry', (string) $container->getAlias(WebhookConfigurationRegistryInterface::class));
        self::assertSame([['add', ['default', [
            'success' => ['url' => 'https://example.com/success'],
            'error' => ['route' => ['webhook_error', []], 'method' => 'PATCH'],
        ]]]], $registry->getMethodCalls());
    }

    public function testDefaultWebhookIsMergedIntoBuildersSupportingIt(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([[
            'webhook' => [
                'default' => [
                    'success' => ['url' => 'https://example.com/success', 'method' => 'PUT'],
                    'extra_http_headers' => ['X-Token' => 'secret'],
                ],
            ],
            'default_options' => [
                'webhook' => 'default',
                'pdf' => ['url' => ['webhook' => ['error' => ['url' => 'https://example.com/error']]]],
            ],
        ]], $container);

        $values = $container->getDefinition('daif_chrome_pdf.builder_configurator')->getArgument(1);
        self::assertIsArray($values);

        self::assertSame([
            'success' => ['url' => 'https://example.com/success', 'method' => 'PUT'],
            'extra_http_headers' => ['X-Token' => 'secret'],
        ], $values[HtmlPdfBuilder::class]['webhook'] ?? null);
        self::assertSame([
            'success' => ['url' => 'https://example.com/success', 'method' => 'PUT'],
            'extra_http_headers' => ['X-Token' => 'secret'],
            'error' => ['url' => 'https://example.com/error'],
        ], $values[UrlPdfBuilder::class]['webhook'] ?? null);
        self::assertSame([
            'success' => ['url' => 'https://example.com/success', 'method' => 'PUT'],
            'extra_http_headers' => ['X-Token' => 'secret'],
        ], $values[UrlScreenshotBuilder::class]['webhook'] ?? null);
    }

    public function testUnknownDefaultWebhookIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The webhook configuration "missing" is not defined');

        self::getExtension()->load([['default_options' => ['webhook' => 'missing']]], self::getContainerBuilder());
    }

    public function testAsyncIsDisabledByDefault(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([], $container);

        self::assertFalse($container->hasDefinition('daif_chrome_pdf.async_generator'));
        self::assertFalse($container->hasDefinition('daif_chrome_pdf.messenger.generate_document_handler'));
        self::assertTrue($container->hasDefinition('.daif_chrome_pdf.webhook_configuration_registry'));
    }

    public function testAsyncBusCanBeConfigured(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([['async' => ['enabled' => true, 'bus' => 'messenger.bus.pdf']]], $container);

        self::assertSame('messenger.bus.pdf', (string) $container->getDefinition('daif_chrome_pdf.async_generator')->getArgument('$bus'));
    }

    public function testAsyncServicesAreRegisteredWhenEnabled(): void
    {
        $container = self::getContainerBuilder();
        self::getExtension()->load([['async' => true]], $container);

        self::assertSame(AsyncGenerator::class, $container->getDefinition('daif_chrome_pdf.async_generator')->getClass());
        self::assertSame('messenger.default_bus', (string) $container->getDefinition('daif_chrome_pdf.async_generator')->getArgument('$bus'));

        $handler = $container->getDefinition('daif_chrome_pdf.messenger.generate_document_handler');
        self::assertSame(GenerateDocumentHandler::class, $handler->getClass());
        self::assertSame([['handles' => GenerateDocumentMessage::class]], $handler->getTag('messenger.message_handler'));
        self::assertSame('http_client', (string) $handler->getArgument('$httpClient'));
    }
}
