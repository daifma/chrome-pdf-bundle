<?php

use Daif\ChromePdfBundle\Builder\Pdf\ConvertPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EmbedPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EncryptPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\FlattenPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\LibreOfficePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\RotatePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\SplitPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\StampPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\WatermarkPdfBuilder;
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

    // Office
    $services->set('.daif_chrome_pdf.pdf_builder.office', LibreOfficePdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Merge
    $services->set('.daif_chrome_pdf.pdf_builder.merge', MergePdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Convert
    $services->set('.daif_chrome_pdf.pdf_builder.convert', ConvertPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Split
    $services->set('.daif_chrome_pdf.pdf_builder.split', SplitPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Flatten
    $services->set('.daif_chrome_pdf.pdf_builder.flatten', FlattenPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Encrypt
    $services->set('.daif_chrome_pdf.pdf_builder.encrypt', EncryptPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Embed
    $services->set('.daif_chrome_pdf.pdf_builder.embed', EmbedPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Watermark
    $services->set('.daif_chrome_pdf.pdf_builder.watermark', WatermarkPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Stamp
    $services->set('.daif_chrome_pdf.pdf_builder.stamp', StampPdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;

    // Rotate
    $services->set('.daif_chrome_pdf.pdf_builder.rotate', RotatePdfBuilder::class)
        ->share(false)
        ->parent('.daif_chrome_pdf.abstract_builder')
        ->tag('daif_chrome_pdf.builder')
        ->configurator(service('daif_chrome_pdf.builder_configurator'))
    ;
};
