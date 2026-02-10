<?php

namespace Daif\ChromePdfBundle\DependencyInjection;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Browser\ChromeBrowser;
use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\RequestContext;

/**
 * @phpstan-type DaifChromePdfConfiguration array{
 *      assets_directory: string,
 *      chrome_binary?: string|null,
 *      browser_options?: array<string, mixed>,
 *      request_context?: array{base_uri?: string},
 *      controller_listener: bool,
 *      default_options: array{
 *          pdf: array{
 *              html: array<string, mixed>,
 *              url: array<string, mixed>,
 *              markdown: array<string, mixed>,
 *          },
 *          screenshot: array{
 *              html: array<string, mixed>,
 *              url: array<string, mixed>,
 *              markdown: array<string, mixed>
 *          }
 *      }
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
     * @param array<array-key, DaifChromePdfConfiguration> $config
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
        $container->register('daif_chrome_pdf.browser', ChromeBrowser::class)
            ->setArguments([
                $defaultConfiguration['chrome_binary'] ?? null,
                $defaultConfiguration['browser_options'] ?? [],
                new Reference('logger', \Symfony\Component\DependencyInjection\ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
        ;
        $container->setAlias(BrowserInterface::class, 'daif_chrome_pdf.browser');

        // Request context
        $baseUri = $defaultConfiguration['request_context']['base_uri'] ?? null;
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
            ->replaceArgument(1, $defaultConfiguration['assets_directory'])
        ;

        if (false === $defaultConfiguration['controller_listener']) {
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
        foreach ($defaultConfiguration['default_options'] as $type => $buildersOptions) {
            foreach ($buildersOptions as $builderName => $builderOptions) {
                $class = $this->builderStack->getTypeReverseMapping()[$type][$builderName];
                $configValueMapping[$class] = $defaultConfiguration['default_options'][$type][$builderName];
            }
        }

        $container->getDefinition('daif_chrome_pdf.builder_configurator')
            ->replaceArgument(0, $this->builderStack->getConfigMapping())
            ->replaceArgument(1, $configValueMapping)
        ;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
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
