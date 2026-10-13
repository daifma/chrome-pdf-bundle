<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use PHPUnit\Framework\Attributes\Group;

/**
 * Boots a kernel with the bundle and generates real files with a local Chrome.
 */
#[Group('browser')]
final class BrowserTest extends AbstractChromePdfWebTestCase
{
    private const ENV = ['CHROME_PDF_TEST_BINARY', 'CHROME_PDF_TEST_NO_SANDBOX'];

    protected function setUp(): void
    {
        $binary = ChromeAvailability::binaryOrSkip();

        $this->setEnv('CHROME_PDF_TEST_BINARY', $binary);
        $this->setEnv('CHROME_PDF_TEST_NO_SANDBOX', ChromeAvailability::noSandbox() ? '1' : '0');

        self::bootKernel(['test_case' => 'Browser']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (self::ENV as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }

    public function testItGeneratesAPdfThroughTheFacade(): void
    {
        $facade = self::getContainer()->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        $pdf = $facade->pdf()->html()
            ->contentFile('content.html')
            ->generate()
        ;
        $pdf->processor(new InMemoryProcessor());
        $pdf = $pdf->process();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', substr($pdf, -1024));
    }

    public function testItGeneratesAPngScreenshotThroughTheFacade(): void
    {
        $facade = self::getContainer()->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        $png = $facade->screenshot()->html()
            ->contentFile('content.html')
            ->width(640)
            ->height(480)
            ->clip()
            ->generate()
        ;
        $png->processor(new InMemoryProcessor());
        $png = $png->process();

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $size = getimagesizefromstring($png);
        self::assertNotFalse($size);
        self::assertSame([640, 480], [$size[0], $size[1]]);
    }

    public function testScreenshotIsStreamedWithAnImageContentType(): void
    {
        $facade = self::getContainer()->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        $response = $facade->screenshot()->html()
            ->contentFile('content.html')
            ->fileName('shot.png')
            ->generate()
            ->stream()
        ;

        self::assertSame('image/png', $response->headers->get('Content-Type'));
    }

    public function testTheBrowserServiceIsReusedAcrossGenerations(): void
    {
        $container = self::getContainer();
        $browser = $container->get('daif_chrome_pdf.browser');
        self::assertInstanceOf(BrowserInterface::class, $browser);

        $first = $browser->htmlToPdf('<p>first</p>');
        $second = $browser->htmlToPdf('<p>second</p>');

        self::assertStringStartsWith('%PDF-', $first);
        self::assertStringStartsWith('%PDF-', $second);
        self::assertSame($browser, $container->get('daif_chrome_pdf.browser'));
    }

    private function setEnv(string $name, string $value): void
    {
        $_SERVER[$name] = $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }
}
