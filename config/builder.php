<?php

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Configurator\BuilderConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->tag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
    ;

    $services->set('.daif_chrome_pdf.abstract_builder', AbstractBuilder::class)
        ->abstract()
        ->call('setContainer')
        ->tag('container.service_subscriber')
    ;

    $services->set('daif_chrome_pdf.builder_configurator', BuilderConfigurator::class)
        ->args([
            abstract_arg('Mapping of methods per builder for each configuration key'),
            abstract_arg('Mapping of values per builder for each configuration key'),
        ])
    ;
};
