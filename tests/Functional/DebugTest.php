<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Daif\ChromePdfBundle\DataCollector\ChromePdfDataCollector;
use Daif\ChromePdfBundle\Debug\Builder\TraceableBuilder;
use Daif\ChromePdfBundle\Processor\NullProcessor;
use Daif\ChromePdfBundle\Tests\Functional\Debug\FakeBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DebugTest extends AbstractChromePdfWebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel(['test_case' => 'Debug', 'debug' => true]);
    }

    public function testBuildersAreTraceableInDebugMode(): void
    {
        $facade = self::getContainer()->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        self::assertInstanceOf(TraceableBuilder::class, $facade->pdf()->get('.daif_chrome_pdf.pdf_builder.html'));
        self::assertInstanceOf(TraceableBuilder::class, $facade->screenshot()->get('.daif_chrome_pdf.screenshot_builder.url'));
    }

    public function testCollectorReportsPdfAndScreenshots(): void
    {
        $container = self::getContainer();
        self::assertInstanceOf(FakeBrowser::class, $container->get('daif_chrome_pdf.browser'));

        $facade = $container->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        $pdf = $facade->pdf()->html()
            ->contentFile('content.html')
            ->fileName('report.pdf')
            ->processor(new NullProcessor())
            ->generate()
        ;
        $pdf->process();

        $facade->screenshot()->html()
            ->contentFile('content.html')
            ->width(320) // new/any builder method goes through TraceableBuilder::__call
            ->fileName('shot.png')
            ->processor(new NullProcessor())
            ->generate()
            ->process()
        ;

        $collector = $container->get('daif_chrome_pdf.data_collector');
        self::assertInstanceOf(ChromePdfDataCollector::class, $collector);

        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame(2, $collector->getRequestCount());

        $files = $collector->getFiles();
        self::assertCount(2, $files);

        [$pdfFile, $screenshotFile] = $files;

        self::assertSame('pdf', $pdfFile['type']);
        self::assertSame(HtmlPdfBuilder::class, $pdfFile['builderClass']);
        self::assertSame('report.pdf', $pdfFile['fileName']);
        self::assertSame(['contentFile', 'fileName', 'processor'], array_column($pdfFile['calls'], 'method'));
        self::assertEquals(['landscape' => true], $pdfFile['configuration']['default_options']->getValue(true));

        self::assertSame('screenshot', $screenshotFile['type']);
        self::assertSame(HtmlScreenshotBuilder::class, $screenshotFile['builderClass']);
        self::assertSame('shot.png', $screenshotFile['fileName']);
        self::assertSame(['contentFile', 'width', 'fileName', 'processor'], array_column($screenshotFile['calls'], 'method'));

        $expectedSize = \strlen(FakeBrowser::PDF) + \strlen(FakeBrowser::PNG);
        self::assertSame([$expectedSize, 'B'], $collector->getRequestTotalSize());
    }

    public function testCollectorResolvesDefaultOptionsForFullServiceIdsAndResets(): void
    {
        $container = self::getContainer();
        $facade = $container->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        /** @var HtmlPdfBuilder $builder TraceableBuilder proxying the HTML builder through __call */
        $builder = $facade->pdf()->get('.daif_chrome_pdf.pdf_builder.html');

        $builder
            ->contentFile('content.html')
            ->processor(new NullProcessor())
            ->generate()
            ->process()
        ;

        $collector = $container->get('daif_chrome_pdf.data_collector');
        self::assertInstanceOf(ChromePdfDataCollector::class, $collector);

        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        $files = $collector->getFiles();
        self::assertCount(1, $files);
        self::assertEquals(['landscape' => true], $files[0]['configuration']['default_options']->getValue(true));

        $collector->reset();
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame(0, $collector->getRequestCount());
        self::assertSame([], $collector->getFiles());
    }
}
