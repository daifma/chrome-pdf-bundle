<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'test' => true,
    ]);

    // Values are provided at runtime by BrowserTest through environment variables,
    // which also checks the options are env-placeholder friendly.
    $container->extension('daif_chrome_pdf', [
        'chrome_binary' => '%env(default::CHROME_PDF_TEST_BINARY)%',
        'no_sandbox' => '%env(bool:CHROME_PDF_TEST_NO_SANDBOX)%',
        'timeout' => 60000,
    ]);
};
