<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Daif\ChromePdfBundle\Tests\Functional\Async\WebhookRecorder;
use Daif\ChromePdfBundle\Tests\Functional\Debug\FakeBrowser;
use Psr\Log\NullLogger;

return static function (ContainerConfigurator $container): void {
    // Messages without routing are handled synchronously by the default bus.
    $container->extension('framework', [
        'test' => true,
        'messenger' => [
            'enabled' => true,
        ],
        'http_client' => [
            'enabled' => true,
            'mock_response_factory' => 'test.webhook_recorder',
        ],
    ]);

    $container->extension('daif_chrome_pdf', [
        'async' => [
            'enabled' => true,
        ],
        'assets_directory' => ['%kernel.project_dir%/assets', '%kernel.project_dir%/../../Fixtures/assets'],
        'webhook' => [
            'archive' => [
                'success' => ['url' => 'https://webhook.test/archive', 'method' => 'PUT'],
                'error' => ['url' => 'https://webhook.test/archive-error'],
                'extra_http_headers' => ['X-Token' => 'secret'],
            ],
        ],
    ]);

    $container->services()
        ->set('daif_chrome_pdf.browser', FakeBrowser::class)
        ->set('test.webhook_recorder', WebhookRecorder::class)
        ->set('logger', NullLogger::class)
    ;
};
