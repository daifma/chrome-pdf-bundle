<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\ValueObject\EmbeddedFile;
use Daif\ChromePdfBundle\Enumeration\PdfFormat;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Exception\LogicException;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Messenger\AsyncGenerator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class AsyncTest extends AbstractBuilderTestCase
{
    public function testGenerateAsyncRequiresAWebhookUrl(): void
    {
        $this->extraServices['daif_chrome_pdf.async_generator'] = $this->createMock(AsyncGenerator::class);

        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('webhook URL is required');

        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->generateAsync();
    }

    public function testGenerateAsyncValidatesThePayload(): void
    {
        $this->extraServices['daif_chrome_pdf.async_generator'] = $this->createMock(AsyncGenerator::class);

        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('Content is required');

        $this->createBuilder(HtmlPdfBuilder::class)->webhookUrl('https://example.com/hook')->generateAsync();
    }

    public function testGenerateAsyncRequiresAsyncToBeEnabled(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Async generation is disabled. Install symfony/messenger and symfony/http-client and set daif_chrome_pdf.async.enabled: true.');

        // checked before the payload and the webhook URL
        $this->createBuilder(HtmlPdfBuilder::class)->generateAsync();
    }

    public function testGenerateAsyncDispatchesTheBodyBag(): void
    {
        $generator = $this->createMock(AsyncGenerator::class);
        $this->extraServices['daif_chrome_pdf.async_generator'] = $generator;

        $builder = $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->webhookUrl('https://example.com/hook', 'PUT')
            ->fileName('report.pdf', HeaderUtils::DISPOSITION_ATTACHMENT)
        ;

        $generator->expects(self::once())->method('dispatch')->with(
            HtmlPdfBuilder::class,
            $builder->getBodyBag()->all(),
            'report.pdf',
            HeaderUtils::DISPOSITION_ATTACHMENT,
        )->willReturn('id');

        $builder->generateAsync();
    }

    public function testBodyBagRoundTripsThroughSerialization(): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)
            ->contentFile('files/content.html')
            ->headerFile('files/header.html')
            ->footerRaw('<p>footer</p>')
            ->assets('logo.png')
            ->cookies([new Cookie('session', 'abc', domain: 'example.com'), ['name' => 'lang', 'value' => 'fr', 'domain' => 'example.com']])
            ->embedFiles(new EmbeddedFile(self::FIXTURE_DIR.'/assets/other_logo.png', 'Data'))
            ->pdfFormat(PdfFormat::Pdf2b)
            ->splitMode(SplitMode::Pages)
            ->splitSpan('1')
            ->webhookUrl('https://example.com/hook')
            ->landscape()
        ;

        $data = unserialize(serialize($builder->getBodyBag()->all()));
        self::assertEquals($builder->getBodyBag()->all(), $data);

        $restored = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('stale')->restoreState($data, 'report.pdf', HeaderUtils::DISPOSITION_ATTACHMENT);
        self::assertEquals($builder->getBodyBag()->all(), $restored->getBodyBag()->all());
        self::assertEquals(
            (fn (): array => $this->collectPageOptions())->call($builder),
            (fn (): array => $this->collectPageOptions())->call($restored),
        );

        // post-processing options make generate() need the engine: only check the browser call
        $restored->pdfFormat(null)->splitMode(null)->embedFiles();
        $result = $restored->generate();

        self::assertSame('report.pdf', $result->getFileName());
        self::assertStringContainsString('<h1>Hello world!</h1>', (string) $this->browser->source);
        self::assertSame(['logo.png'], array_keys($this->browser->assetContents));
    }

    public function testFilesRoundTripThroughSerialization(): void
    {
        $markdown = $this->createBuilder(MarkdownPdfBuilder::class)->wrapperRaw('{{ toHTML "file.md" }}')->files('file.md');
        $merge = $this->createBuilder(MergePdfBuilder::class)->files('pdf/document.pdf')->stampFile('logo.png');

        foreach ([$markdown, $merge] as $builder) {
            self::assertEquals($builder->getBodyBag()->all(), unserialize(serialize($builder->getBodyBag()->all())));
        }
    }
}
