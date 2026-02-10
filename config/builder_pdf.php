<?php

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->tag('monolog.logger', ['channel' => 'daif_chrome_pdf'])
    ;

    // HTML
    $services->set('.daif_chrome_pdf.pdf_builder.html', HtmlPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // URL
    $services->set('.daif_chrome_pdf.pdf_builder.url', UrlPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Markdown
    $services->set('.daif_chrome_pdf.pdf_builder.markdown', MarkdownPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;
};
