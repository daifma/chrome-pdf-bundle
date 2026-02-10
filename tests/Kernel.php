<?php

namespace Daif\ChromePdfBundle\Tests;

use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Daif\ChromePdfBundle\DaifChromePdfBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return __DIR__.'/../var/cache';
    }

    public function getLogDir(): string
    {
        return __DIR__.'/../var/log';
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $builder->loadFromExtension('framework', [
            'test' => true,
        ]);
        $builder->loadFromExtension('daif_chrome_pdf', [
            'http_client' => 'http_client',
            'version' => '50000.0.0',
        ]);
        $builder->addCompilerPass($this);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DaifChromePdfBundle();
    }

    public function process(ContainerBuilder $container): void
    {
        $container->getAlias(ChromePdfFacadeInterface::class)->setPublic(true);
        $container->setDefinition('logger', new Definition(NullLogger::class));
    }
}
