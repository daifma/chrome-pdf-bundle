<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\MarkdownScreenshotBuilder;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use PHPUnit\Framework\Attributes\DataProvider;

final class MarkdownBuilderTest extends AbstractBuilderTestCase
{
    private const TABLE = '<th>Syntax</th>';

    /**
     * @return iterable<string, array{class-string<MarkdownPdfBuilder|MarkdownScreenshotBuilder>, string}>
     */
    public static function builders(): iterable
    {
        yield 'pdf' => [MarkdownPdfBuilder::class, 'htmlToPdf'];
        yield 'screenshot' => [MarkdownScreenshotBuilder::class, 'htmlToScreenshot'];
    }

    /**
     * @param class-string<MarkdownPdfBuilder|MarkdownScreenshotBuilder> $class
     */
    #[DataProvider('builders')]
    public function testVerbatimTwigWrapper(string $class, string $method): void
    {
        $this->createBuilder($class)
            ->wrapper('wrapper.html.twig', ['name' => 'Markdown'])
            ->files('file.md')
            ->generate()
        ;

        self::assertSame($method, $this->browser->method);
        $html = (string) $this->browser->source;
        self::assertStringContainsString('<h1>Hello Markdown!</h1>', $html);
        self::assertStringContainsString(self::TABLE, $html);
        self::assertStringNotContainsString('toHTML', $html);
    }

    /**
     * @param class-string<MarkdownPdfBuilder|MarkdownScreenshotBuilder> $class
     */
    #[DataProvider('builders')]
    public function testPrintedDirectiveInTwigWrapper(string $class): void
    {
        // {{ '{{ toHTML "file.md" }}' }} is HTML-escaped by Twig into &quot;, which must still be recognized.
        $this->createBuilder($class)->wrapper('wrapper_print.html.twig', ['name' => 'Markdown'])->files('file.md')->generate();

        self::assertStringContainsString(self::TABLE, (string) $this->browser->source);
        self::assertStringNotContainsString('toHTML', (string) $this->browser->source);
    }

    /**
     * @param class-string<MarkdownPdfBuilder|MarkdownScreenshotBuilder> $class
     */
    #[DataProvider('builders')]
    public function testUnprotectedDirectiveIsRejectedByTwig(string $class): void
    {
        $this->expectException(PartRenderingException::class);
        $this->expectExceptionMessage('Could not render template "wrapper_unprotected.html.twig"');

        $this->createBuilder($class)->wrapper('wrapper_unprotected.html.twig');
    }

    /**
     * @param class-string<MarkdownPdfBuilder|MarkdownScreenshotBuilder> $class
     */
    #[DataProvider('builders')]
    public function testHtmlWrapperFile(string $class): void
    {
        $this->createBuilder($class)->wrapperFile('files/wrapper.html')->files('file.md')->generate();

        self::assertStringContainsString(self::TABLE, (string) $this->browser->source);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function directives(): iterable
    {
        yield 'canonical' => ['{{ toHTML "file.md" }}'];
        yield 'no spaces' => ['{{toHTML "file.md"}}'];
        yield 'many spaces' => ['{{   toHTML    "file.md"   }}'];
        yield 'single quotes' => ["{{ toHTML 'file.md' }}"];
        yield 'escaped quotes' => ['{{ toHTML &quot;file.md&quot; }}'];
        yield 'numeric entity quotes' => ['{{ toHTML &#39;file.md&#39; }}'];
        yield 'trim markers' => ['{{- toHTML "file.md" -}}'];
        yield 'new lines' => ["{{\n toHTML\n \"file.md\"\n}}"];
    }

    #[DataProvider('directives')]
    public function testDirectiveSyntaxIsTolerant(string $directive): void
    {
        $this->createBuilder(MarkdownPdfBuilder::class)->wrapperRaw('<body>'.$directive.'</body>')->files('file.md')->generate();

        self::assertStringStartsWith('<body><h1>', (string) $this->browser->source);
        self::assertStringContainsString(self::TABLE, (string) $this->browser->source);
    }

    public function testSeveralFilesAndReferencesByPath(): void
    {
        $this->createBuilder(MarkdownPdfBuilder::class)
            ->wrapperRaw('<main>{{ toHTML "markdown/chapter.md" }}|{{ toHTML "chapter.md" }}|{{ toHTML "file.md" }}</main>')
            ->files('markdown/chapter.md', 'file.md')
            ->generate()
        ;

        $parts = explode('|', (string) $this->browser->source);
        self::assertCount(3, $parts);
        self::assertStringContainsString('<h1>Chapter</h1>', $parts[0]);
        self::assertStringContainsString('<h1>Chapter</h1>', $parts[1]);
        self::assertStringContainsString(self::TABLE, $parts[2]);
    }

    public function testWindowsStyleReferencesAndPaths(): void
    {
        $this->createBuilder(MarkdownScreenshotBuilder::class)
            ->wrapperRaw('{{ toHTML "markdown\\chapter.md" }}')
            ->files('markdown\\chapter.md')
            ->generate()
        ;

        self::assertStringContainsString('<h1>Chapter</h1>', (string) $this->browser->source);
    }

    public function testAmbiguousBasenameIsRejected(): void
    {
        $builder = $this->createBuilder(MarkdownPdfBuilder::class)
            ->wrapperRaw('{{ toHTML "file.md" }}')
            ->files(self::FIXTURE_DIR.'/assets/file.md', 'markdown/file.md')
        ;

        $this->expectException(PartRenderingException::class);
        $this->expectExceptionMessage('ambiguous');

        $builder->generate();
    }

    public function testAmbiguousBasenameCanBeReferencedByPath(): void
    {
        $this->createBuilder(MarkdownPdfBuilder::class)
            ->wrapperRaw('{{ toHTML "markdown/file.md" }}')
            ->files('file.md', 'markdown/file.md')
            ->generate()
        ;

        self::assertStringContainsString('<h1>Second file</h1>', (string) $this->browser->source);
    }

    /**
     * @param class-string<MarkdownPdfBuilder|MarkdownScreenshotBuilder> $class
     */
    #[DataProvider('builders')]
    public function testUnknownReferenceIsRejected(string $class): void
    {
        $builder = $this->createBuilder($class)->wrapperRaw('{{ toHTML "missing.md" }}')->files('file.md');

        $this->expectException(PartRenderingException::class);
        $this->expectExceptionMessage('The markdown wrapper references "missing.md" but no such markdown file was provided.');

        $builder->generate();
    }

    public function testWrapperIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('HTML template is required');

        $this->createBuilder(MarkdownPdfBuilder::class)->files('file.md')->generate();
    }

    public function testFilesAreRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('At least one markdown file is required.');

        $this->createBuilder(MarkdownScreenshotBuilder::class)->wrapperRaw('x')->generate();
    }

    public function testOnlyMarkdownFilesAreAccepted(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(MarkdownPdfBuilder::class)->files('logo.png');
    }

    public function testContentMethodsAreHidden(): void
    {
        foreach ([MarkdownPdfBuilder::class, MarkdownScreenshotBuilder::class] as $class) {
            foreach (['content', 'contentRaw', 'contentFile'] as $method) {
                self::assertTrue((new \ReflectionMethod($class, $method))->isPrivate(), $class.'::'.$method);
            }
        }
    }
}
