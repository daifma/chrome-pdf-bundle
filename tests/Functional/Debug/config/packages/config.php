<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Daif\ChromePdfBundle\Tests\Functional\Debug\FakeBrowser;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'test' => true,
    ]);

    $container->extension('daif_chrome_pdf', [
        'default_options' => [
            'pdf' => [
                'html' => [
                    'landscape' => true,
                ],
            ],
        ],
    ]);

    $container->services()
        ->set('daif_chrome_pdf.browser', FakeBrowser::class)
    ;
};
