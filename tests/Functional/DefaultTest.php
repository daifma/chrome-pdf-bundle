<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Symfony\Component\DependencyInjection\ContainerInterface;

class DefaultTest extends AbstractChromePdfWebTestCase
{
    public function testBundleDefault(): void
    {
        $kernel = static::createKernel(['test_case' => 'Default']);
        $kernel->boot();

        // Asynchronous generation is opt-in (daif_chrome_pdf.async.enabled).
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertFalse($container->has('daif_chrome_pdf.async_generator'));
        self::assertFalse($container->has('daif_chrome_pdf.messenger.generate_document_handler'));
        self::assertTrue($container->has('daif_chrome_pdf.engine.qpdf'));
    }
}
