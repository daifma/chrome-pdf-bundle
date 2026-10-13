<?php

namespace Daif\ChromePdfBundle\DependencyInjection;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Browser\ChromeBrowser;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\LibreOffice;
use Daif\ChromePdfBundle\Engine\PdfPostProcessor;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Messenger\AsyncGenerator;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentHandler;
use Daif\ChromePdfBundle\Messenger\GenerateDocumentMessage;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistry;
use Daif\ChromePdfBundle\Webhook\WebhookConfigurationRegistryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * @phpstan-type WebhookEndpoint array{url?: string, route?: array{0: string, 1: array<string|int, mixed>}, method?: 'POST'|'PUT'|'PATCH'|null}
 * @phpstan-type NamedWebhookConfiguration array{success: WebhookEndpoint, error?: WebhookEndpoint, events?: WebhookEndpoint, extra_http_headers?: array<string, mixed>}
 * @phpstan-type DaifChromePdfConfiguration array{
 *      assets_directory: list<string>,
 *      chrome_binary: string|null,
 *      timeout: int,
 *      no_sandbox: bool,
 *      ignore_certificate_errors: bool,
 *      temp_dir: string|null,
 *      qpdf_binary: string|null,
 *      exiftool_binary: string|null,
 *      libreoffice_binary: string|null,
 *      engine_timeout: int,
 *      allow_list: list<string>,
 *      deny_list: list<string>,
 *      local_file_roots: list<string>,
 *      browser_options: array<string, mixed>,
 *      request_context?: array{base_uri?: string},
 *      controller_listener: bool,
 *      async: array{enabled: bool, bus: string},
 *      webhook: array<string, NamedWebhookConfiguration>,
 *      default_options: array<string, mixed>,
 *  }
 */
class DaifChromePdfExtension extends Extension
{
    private BuilderStack $builderStack;

    public function __construct()
    {
        $this->builderStack = new BuilderStack();
    }

    /**
     * @param class-string<BuilderInterface> $class
     */
    public function registerBuilder(string $class): void
    {
        $this->builderStack->push($class);
    }

    /**
     * @param class-string<BuilderInterface> $class
     */
    public function getBuilder(string $class): string
    {
        return $this->builderStack->getBuilders()[$class] ?? throw new \InvalidArgumentException(\sprintf('The builder "%s" is not registered.', $class));
    }

    /**
     * @deprecated The BuilderStack is created by the extension itself
     */
    public function setBuilderStack(BuilderStack $builderStack): void
    {
        $this->builderStack = $builderStack;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->builderStack->getConfigNode());
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);

        /**
         * @var DaifChromePdfConfiguration $config
         */
        $config = $this->processConfiguration($configuration, $configs);
        $defaultConfiguration = $this->processDefaultConfiguration($config);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));

        // Services
        $loader->load('services.php');

        // Builders
        $loader->load('builder.php');
        $loader->load('builder_pdf.php');
        $loader->load('builder_screenshot.php');

        // Browser
        // Shared, long-lived service: the Chrome process is reused across requests/messages.
        // It is intentionally NOT tagged "kernel.reset" (that would kill and respawn Chrome
        // after every request or Messenger message). The process is closed by
        // ChromeBrowser::__destruct() and by chrome-php's shutdown function.
        $container->register('daif_chrome_pdf.browser', ChromeBrowser::class)
            ->setArguments([
                '$chromeBinary' => $config['chrome_binary'],
                '$browserOptions' => $config['browser_options'],
                '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$timeout' => $config['timeout'],
                '$noSandbox' => $config['no_sandbox'],
                '$ignoreCertificateErrors' => $config['ignore_certificate_errors'],
                '$tempDir' => $config['temp_dir'],
                '$allowList' => $config['allow_list'],
                '$denyList' => $config['deny_list'],
                '$localFileRoots' => $config['local_file_roots'],
            ])
            ->addTag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
        ;
        $container->setAlias(BrowserInterface::class, 'daif_chrome_pdf.browser');

        $container->register('.daif_chrome_pdf.download_guard', DownloadGuard::class)
            ->setArguments([
                '$allowList' => $config['allow_list'],
                '$denyList' => $config['deny_list'],
                '$tempDir' => $config['temp_dir'],
            ])
        ;

        $this->registerEngines($container, $config);
        $this->registerWebhooks($container, $config);

        // Request context
        $baseUri = $config['request_context']['base_uri'] ?? null;
        if (null !== $baseUri) {
            $container
                ->register('.daif_chrome_pdf.request_context', RequestContext::class)
                ->setFactory([RequestContext::class, 'fromUri'])
                ->setArguments([$baseUri])
            ;
        }

        // Asset base dir formatter
        $container
            ->getDefinition('.daif_chrome_pdf.asset.base_dir_formatter')
            ->replaceArgument(1, $config['assets_directory'])
        ;

        if (false === $config['controller_listener']) {
            $container->removeDefinition('daif_chrome_pdf.http_kernel.stream_builder');
        }

        if ($container->getParameter('kernel.debug') === true) {
            $loader->load('debug.php');
            $container->getDefinition('daif_chrome_pdf.data_collector')
                ->replaceArgument(3, array_filter($defaultConfiguration['default_options'], 'is_array'))
            ;
        }

        $container->registerForAutoconfiguration(BuilderInterface::class)
            ->addTag('daif_chrome_pdf.builder')
            ->setConfigurator(new Reference('daif_chrome_pdf.builder_configurator'))
        ;

        $container->registerForAutoconfiguration(AbstractBuilder::class);

        // Configurators
        $configValueMapping = [];
        $typeReverseMapping = $this->builderStack->getTypeReverseMapping();
        foreach ($defaultConfiguration['default_options'] as $type => $buildersOptions) {
            if ('webhook' === $type || !\is_array($buildersOptions)) {
                continue;
            }

            foreach ($buildersOptions as $builderName => $builderOptions) {
                $class = $typeReverseMapping[$type][$builderName] ?? null;
                if (null === $class) {
                    continue;
                }
                $configValueMapping[$class] = $builderOptions;
            }
        }
        // Builders without any configuration node still need an entry for the configurator.
        $configMapping = $this->builderStack->getConfigMapping();
        foreach (array_keys($this->builderStack->getBuilders()) as $class) {
            $configValueMapping[$class] ??= [];
            $configMapping[$class] ??= [];
        }

        $container->getDefinition('daif_chrome_pdf.builder_configurator')
            ->replaceArgument(0, $configMapping)
            ->replaceArgument(1, $configValueMapping)
        ;
    }

    /**
     * @param DaifChromePdfConfiguration $config
     *
     * @return DaifChromePdfConfiguration
     */
    private function processDefaultConfiguration(array $config): array
    {
        $typeReverseMapping = $this->builderStack->getTypeReverseMapping();
        $configMapping = $this->builderStack->getConfigMapping();
        /** @var string|null $defaultWebhookName */
        $defaultWebhookName = $config['default_options']['webhook'] ?? null;

        foreach ($config['default_options'] as $type => $builders) {
            if ('webhook' === $type || !\is_array($builders)) {
                continue;
            }

            foreach ($builders as $builderName => $builderOptions) {
                /** @var array<string, mixed> $builderOptions */
                $class = $typeReverseMapping[$type][$builderName] ?? null;
                if (null !== $class && isset($configMapping[$class]['webhook'])) {
                    $builderOptions['webhook'] = $this->resolveBuilderWebhook($config['webhook'], $builderOptions['webhook'] ?? null, $defaultWebhookName);
                }

                $config['default_options'][$type][$builderName] = $this->cleanBuilderConfiguration($builderOptions);
            }
        }

        return $config;
    }

    /**
     * Merges the named webhook configuration (from the builder "config_name" or default_options.webhook) with the builder one.
     *
     * @param array<string, NamedWebhookConfiguration> $namedConfigurations
     *
     * @return array<string, mixed>
     */
    private function resolveBuilderWebhook(array $namedConfigurations, mixed $builderWebhook, string|null $defaultWebhookName): array
    {
        $builderWebhookConfig = [];
        $builderWebhookConfigName = null;
        if (\is_array($builderWebhook)) {
            $builderWebhookConfig = $builderWebhook;
            $builderWebhookConfigName = \is_string($builderWebhook['config_name'] ?? null) ? $builderWebhook['config_name'] : null;
        } elseif (\is_string($builderWebhook)) {
            $builderWebhookConfigName = $builderWebhook;
        }

        $webhookConfigName = $builderWebhookConfigName ?? $defaultWebhookName;
        $defaultWebhookConfig = [];
        if (null !== $webhookConfigName) {
            $defaultWebhookConfig = $namedConfigurations[$webhookConfigName] ?? throw new \InvalidArgumentException(\sprintf('The webhook configuration "%s" is not defined under "daif_chrome_pdf.webhook".', $webhookConfigName));
        }

        /** @var array<string, mixed> $builderWebhookConfig */
        return array_merge(
            $this->cleanBuilderConfiguration($defaultWebhookConfig),
            $this->cleanBuilderConfiguration($builderWebhookConfig),
        );
    }

    /**
     * Removes unset endpoints and keys, keeping the route parameters (even empty).
     *
     * @param NamedWebhookConfiguration $configuration
     *
     * @return NamedWebhookConfiguration
     */
    private static function cleanWebhookConfiguration(array $configuration): array
    {
        foreach (['success', 'error', 'events'] as $type) {
            if (!isset($configuration[$type])) {
                continue;
            }

            $configuration[$type] = array_filter($configuration[$type], static fn (mixed $value): bool => null !== $value);
        }

        if ([] === ($configuration['extra_http_headers'] ?? null)) {
            unset($configuration['extra_http_headers']);
        }

        return $configuration;
    }

    /**
     * @param DaifChromePdfConfiguration $config
     */
    private function registerEngines(ContainerBuilder $container, array $config): void
    {
        $container->register('.daif_chrome_pdf.engine.binary_finder', BinaryFinder::class);

        $engines = [
            'daif_chrome_pdf.engine.qpdf' => [Qpdf::class, $config['qpdf_binary']],
            'daif_chrome_pdf.engine.exiftool' => [Exiftool::class, $config['exiftool_binary']],
            'daif_chrome_pdf.engine.libreoffice' => [LibreOffice::class, $config['libreoffice_binary']],
        ];

        foreach ($engines as $id => [$class, $binary]) {
            $container->register($id, $class)
                ->setArguments([
                    '$binaryFinder' => new Reference('.daif_chrome_pdf.engine.binary_finder'),
                    '$binary' => $binary,
                    '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                    '$timeout' => $config['engine_timeout'],
                    '$tempDir' => $config['temp_dir'],
                ])
                ->addTag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
            ;
            $container->setAlias($class, $id);
        }

        $container->register('daif_chrome_pdf.engine.post_processor', PdfPostProcessor::class)
            ->setArguments([
                '$qpdf' => new Reference('daif_chrome_pdf.engine.qpdf'),
                '$exiftool' => new Reference('daif_chrome_pdf.engine.exiftool'),
                '$libreOffice' => new Reference('daif_chrome_pdf.engine.libreoffice'),
                '$browser' => new Reference('daif_chrome_pdf.browser'),
                '$tempDir' => $config['temp_dir'],
            ])
        ;
        $container->setAlias(PdfPostProcessor::class, 'daif_chrome_pdf.engine.post_processor');
    }

    /**
     * @param DaifChromePdfConfiguration $config
     */
    private function registerWebhooks(ContainerBuilder $container, array $config): void
    {
        $registry = $container->register('.daif_chrome_pdf.webhook_configuration_registry', WebhookConfigurationRegistry::class)
            ->setArguments([
                new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('.daif_chrome_pdf.request_context', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
        ;
        foreach ($config['webhook'] as $name => $webhookConfig) {
            $registry->addMethodCall('add', [$name, self::cleanWebhookConfiguration($webhookConfig)]);
        }
        $container->setAlias(WebhookConfigurationRegistryInterface::class, '.daif_chrome_pdf.webhook_configuration_registry');

        if (!$config['async']['enabled']) {
            return;
        }

        // Async generation needs both Messenger (dispatch) and an HTTP client (webhook calls).
        if (!interface_exists(MessageBusInterface::class) || !class_exists(HttpClient::class)) {
            throw new InvalidConfigurationException('"daif_chrome_pdf.async.enabled" requires symfony/messenger and symfony/http-client. Try to run "composer require symfony/messenger symfony/http-client".');
        }

        $container->register('daif_chrome_pdf.async_generator', AsyncGenerator::class)
            ->setArguments([
                '$bus' => new Reference($config['async']['bus']),
            ])
        ;
        $container->setAlias(AsyncGenerator::class, 'daif_chrome_pdf.async_generator');

        $container->register('daif_chrome_pdf.messenger.generate_document_handler', GenerateDocumentHandler::class)
            ->setArguments([
                '$builders' => null,
                '$httpClient' => new Reference('http_client'),
                '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$urlGuard' => new Reference('.daif_chrome_pdf.download_guard'),
            ])
            ->addTag('messenger.message_handler', ['handles' => GenerateDocumentMessage::class])
            ->addTag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
        ;
    }

    /**
     * @param array<string, mixed> $userConfigurations
     *
     * @return array<string, mixed>
     */
    private function cleanBuilderConfiguration(array $userConfigurations): array
    {
        foreach ($userConfigurations as $key => $value) {
            if (\is_array($value)) {
                $userConfigurations[$key] = $this->cleanBuilderConfiguration($value);

                if ([] === $userConfigurations[$key]) {
                    unset($userConfigurations[$key]);
                }
            } elseif (null === $value) {
                unset($userConfigurations[$key]);
            }
        }

        return $userConfigurations;
    }
}
