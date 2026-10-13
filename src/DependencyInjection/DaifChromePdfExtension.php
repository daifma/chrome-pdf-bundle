<?php

namespace Daif\ChromePdfBundle\DependencyInjection;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Browser\ChromeBrowser;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\RequestContext;

/**
 * @phpstan-type DaifChromePdfConfiguration array{
 *      assets_directory: list<string>,
 *      chrome_binary: string|null,
 *      timeout: int,
 *      no_sandbox: bool,
 *      ignore_certificate_errors: bool,
 *      temp_dir: string|null,
 *      allow_list: list<string>,
 *      deny_list: list<string>,
 *      browser_options: array<string, mixed>,
 *      request_context?: array{base_uri?: string},
 *      controller_listener: bool,
 *      default_options: array<string, array<string, array<string, mixed>>>,
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
                ->replaceArgument(3, $defaultConfiguration['default_options'])
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
        foreach ($config['default_options'] as $type => $builders) {
            foreach ($builders as $builderName => $builderOptions) {
                $config['default_options'][$type][$builderName] = $this->cleanBuilderConfiguration($config['default_options'][$type][$builderName]);
            }
        }

        return $config;
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
