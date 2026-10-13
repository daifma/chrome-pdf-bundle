<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Inspired by Symfony's AppKernel.
 *
 * @see https://github.com/symfony/symfony/blob/7.4/src/Symfony/Bundle/FrameworkBundle/Tests/Functional/app/AppKernel.php
 */
final class TestKernel extends BaseKernel implements ExtensionInterface, ConfigurationInterface, CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * Services made public so functional tests can fetch them even when unused.
     */
    private const PUBLIC_SERVICES = [
        ChromePdfFacadeInterface::class,
        'daif_chrome_pdf.browser',
        'daif_chrome_pdf.data_collector',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly string $tmpDir,
        bool $debug = false,
    ) {
        parent::__construct('test', $debug);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->tmpDir.'/cache/'.($this->debug ? 'debug' : 'nodebug');
    }

    public function getLogDir(): string
    {
        return $this->tmpDir.'/logs';
    }

    protected function getContainerBuilder(): ContainerBuilder
    {
        $container = parent::getContainerBuilder();
        // Symfony >= 7.4 writes config/reference.php in debug mode: keep it out of the source tree.
        if ($container->hasParameter('.kernel.config_dir')) {
            $container->setParameter('.kernel.config_dir', $this->tmpDir);
        }

        return $container;
    }

    public function process(ContainerBuilder $container): void
    {
        foreach (self::PUBLIC_SERVICES as $id) {
            if ($container->hasAlias($id)) {
                $container->getAlias($id)->setPublic(true);
            } elseif ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getNamespace(): string
    {
        return '';
    }

    public function getXsdValidationBasePath(): bool
    {
        return false;
    }

    public function getAlias(): string
    {
        return 'foo';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('foo');
    }
}
