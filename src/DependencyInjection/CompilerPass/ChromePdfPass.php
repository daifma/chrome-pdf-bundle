<?php

namespace Daif\ChromePdfBundle\DependencyInjection\CompilerPass;

use Daif\ChromePdfBundle\Builder\BuilderInterface;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Daif\ChromePdfBundle\DependencyInjection\DaifChromePdfExtension;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

final class ChromePdfPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var DaifChromePdfExtension $extension */
        $extension = $container->getExtension('daif_chrome_pdf');
        $builderPerType = [];
        foreach ($container->findTaggedServiceIds('daif_chrome_pdf.builder') as $serviceId => $tags) {
            $serviceDefinition = $container->getDefinition($serviceId);
            $serviceDefinition
                ->setShared(false)
                ->addTag('container.service_subscriber')
            ;

            /** @var class-string<BuilderInterface> $class */
            $class = $serviceDefinition->getClass();
            $type = $extension->getBuilder($class);

            $builderPerType[$type] ??= [];
            $builderPerType[$type][$serviceId] = new Reference($serviceId);
        }

        if ($container->hasDefinition('daif_chrome_pdf.pdf')) {
            $container->getDefinition('daif_chrome_pdf.pdf')
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $builderPerType['pdf']))
            ;
        }

        if ($container->hasDefinition('daif_chrome_pdf.screenshot')) {
            $container->getDefinition('daif_chrome_pdf.screenshot')
                ->replaceArgument(0, ServiceLocatorTagPass::register($container, $builderPerType['screenshot']))
            ;
        }

        if (!$container->has('daif_chrome_pdf.data_collector')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('daif_chrome_pdf.builder') as $serviceId => $tags) {
            $container
                ->register('.debug.'.ltrim($serviceId, '.'), TraceableBuilder::class)
                ->setDecoratedService($serviceId)
                ->setShared(false)
                ->setArguments([
                    '$inner' => new Reference('.inner'),
                    '$stopwatch' => new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
            ;
        }
    }
}
