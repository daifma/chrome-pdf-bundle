<?php

use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->tag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
    ;

    // HTML
    $services->set('.daif_chrome_pdf.screenshot_builder.html', HtmlScreenshotBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Markdown
    $services->set('.daif_chrome_pdf.screenshot_builder.markdown', MarkdownScreenshotBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // URL
    $services->set('.daif_chrome_pdf.screenshot_builder.url', UrlScreenshotBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;
};
