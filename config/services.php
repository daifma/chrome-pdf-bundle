<?php

use Daif\ChromePdfBundle\ChromePdf;
use Daif\ChromePdfBundle\ChromePdfFacade;
use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\ChromeScreenshot;
use Daif\ChromePdfBundle\ChromeScreenshotInterface;
use Daif\ChromePdfBundle\EventListener\ProcessBuilderOnControllerResponse;
use Daif\ChromePdfBundle\Formatter\AssetBaseDirFormatter;
use Daif\ChromePdfBundle\Twig\ChromePdfExtension;
use Daif\ChromePdfBundle\Twig\ChromePdfRuntime;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('.daif_chrome_pdf.asset.base_dir_formatter', AssetBaseDirFormatter::class)
        ->args([
            param('kernel.project_dir'),
            abstract_arg('assets_directory to assets'),
        ])
        ->alias(AssetBaseDirFormatter::class, '.daif_chrome_pdf.asset.base_dir_formatter')
    ;

    $services->set('daif_chrome_pdf.twig.asset_extension', ChromePdfExtension::class)
        ->tag('twig.extension')
    ;
    $services->set('daif_chrome_pdf.twig.asset_runtime', ChromePdfRuntime::class)
        ->args([
            service('assets.packages')->nullOnInvalid(),
            service('asset_mapper.repository')->nullOnInvalid(),
        ])
        ->tag('twig.runtime')
    ;

    $services->set('daif_chrome_pdf.pdf', ChromePdf::class)
        ->args([
            abstract_arg('PDF builders services'),
        ])
        ->alias(ChromePdfInterface::class, 'daif_chrome_pdf.pdf')
    ;

    $services->set('daif_chrome_pdf.screenshot', ChromeScreenshot::class)
        ->args([
            abstract_arg('Screenshot builders services'),
        ])
        ->alias(ChromeScreenshotInterface::class, 'daif_chrome_pdf.screenshot')
    ;

    $services->set('daif_chrome_pdf', ChromePdfFacade::class)
        ->args([
            service_locator([
                ChromePdfInterface::class => service('daif_chrome_pdf.pdf'),
                ChromeScreenshotInterface::class => service('daif_chrome_pdf.screenshot'),
            ]),
        ])
        ->alias(ChromePdfFacadeInterface::class, 'daif_chrome_pdf')
    ;

    $services->set('daif_chrome_pdf.http_kernel.stream_builder', ProcessBuilderOnControllerResponse::class)
        ->tag('kernel.event_listener', ['method' => 'streamBuilder', 'event' => 'kernel.view'])
    ;
};
