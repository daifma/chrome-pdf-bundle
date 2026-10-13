<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Log\NullLogger;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'test' => true,
    ]);

    // Engine binaries are auto-detected (PATH, QPDF_PATH, EXIFTOOL_PATH, LIBREOFFICE_PATH). Chrome is only
    // needed by text stamps, its settings are provided by EngineTest through environment variables.
    $container->extension('daif_chrome_pdf', [
        'assets_directory' => '%kernel.project_dir%/../../Fixtures/assets',
        'chrome_binary' => '%env(default::CHROME_PDF_TEST_BINARY)%',
        'no_sandbox' => '%env(bool:default::CHROME_PDF_TEST_NO_SANDBOX)%',
        'timeout' => 60000,
        'engine_timeout' => 180000,
    ]);

    $container->services()
        ->set('logger', NullLogger::class)
    ;
};
