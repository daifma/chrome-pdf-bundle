<?php

use Daif\ChromePdfBundle\DataCollector\ChromePdfDataCollector;
use Daif\ChromePdfBundle\Debug\TraceableChromePdf;
use Daif\ChromePdfBundle\Debug\TraceableChromePdfScreenshot;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('daif_chrome_pdf.traceable_pdf', TraceableChromePdf::class)
        ->decorate('daif_chrome_pdf.pdf')
        ->args([
            new Reference('.inner'),
        ])
    ;

    $services->set('daif_chrome_pdf.traceable_screenshot', TraceableChromePdfScreenshot::class)
        ->decorate('daif_chrome_pdf.screenshot')
        ->args([
            new Reference('.inner'),
        ])
    ;

    $services->set('daif_chrome_pdf.data_collector', ChromePdfDataCollector::class)
        ->args([
            service('daif_chrome_pdf.pdf'),
            service('daif_chrome_pdf.screenshot'),
            tagged_locator('daif_chrome_pdf.builder'),
            abstract_arg('All default options will be set through the configuration.'),
        ])
        ->tag('data_collector', ['template' => '@DaifChromePdf/Collector/daif_chrome_pdf.html.twig', 'id' => 'daif_chrome_pdf'])
    ;
};
