<?php

namespace Daif\ChromePdfBundle\Tests\Builder\Screenshot;

use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Tests\Builder\AbstractBuilderTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ScreenshotBuilderTest extends AbstractBuilderTestCase
{
    public function testEveryScreenshotOption(): void
    {
        $this->createBuilder(HtmlScreenshotBuilder::class)
            ->contentRaw('x')
            ->width(1024)
            ->height(768)
            ->deviceScaleFactor(2)
            ->clip()
            ->format(ScreenshotFormat::Jpeg)
            ->quality(80)
            ->optimizeForSpeed()
            ->omitBackground()
            ->generate()
        ;

        self::assertSame('htmlToScreenshot', $this->browser->method);
        self::assertSame([
            'width' => 1024,
            'height' => 768,
            'deviceScaleFactor' => 2.0,
            'clip' => true,
            'optimizeForSpeed' => true,
            'format' => 'jpeg',
            'quality' => 80,
        ], $this->browser->options);
        self::assertSame(['waitEvent' => 'load', 'omitBackground' => true], $this->browser->pageOptions);
    }

    public function testDefaultScreenshotOptionsAreEmpty(): void
    {
        $this->createBuilder(HtmlScreenshotBuilder::class)->contentRaw('x')->generate();

        self::assertSame([], $this->browser->options);
    }

    public function testQualityIsDroppedForPng(): void
    {
        $this->createBuilder(HtmlScreenshotBuilder::class)->contentRaw('x')->quality(50)->generate();
        self::assertSame([], $this->browser->options);

        $this->createBuilder(HtmlScreenshotBuilder::class)->contentRaw('x')->format(ScreenshotFormat::Png)->quality(50)->generate();
        self::assertSame(['format' => 'png'], $this->browser->options);
    }

    /**
     * @return iterable<string, array{ScreenshotFormat|null, string}>
     */
    public static function contentTypes(): iterable
    {
        yield 'default' => [null, 'image/png'];
        yield 'png' => [ScreenshotFormat::Png, 'image/png'];
        yield 'jpeg' => [ScreenshotFormat::Jpeg, 'image/jpeg'];
        yield 'webp' => [ScreenshotFormat::Webp, 'image/webp'];
    }

    #[DataProvider('contentTypes')]
    public function testResultHasTheImageContentType(ScreenshotFormat|null $format, string $expected): void
    {
        foreach ([HtmlScreenshotBuilder::class, UrlScreenshotBuilder::class] as $class) {
            $builder = $this->createBuilder($class);
            $builder instanceof UrlScreenshotBuilder ? $builder->url('https://example.com') : $builder->contentRaw('x');
            if (null !== $format) {
                $builder->format($format);
            }

            $result = $builder->generate();

            self::assertSame($expected, $result->getContentType());
            self::assertSame($expected, $result->stream()->headers->get('Content-Type'));
        }
    }

    /**
     * @return iterable<string, array{\Closure(HtmlScreenshotBuilder): mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'width' => [static fn (HtmlScreenshotBuilder $b) => $b->width(0)];
        yield 'height' => [static fn (HtmlScreenshotBuilder $b) => $b->height(-1)];
        yield 'deviceScaleFactor' => [static fn (HtmlScreenshotBuilder $b) => $b->deviceScaleFactor(0)];
        yield 'quality' => [static fn (HtmlScreenshotBuilder $b) => $b->quality(101)]; // @phpstan-ignore argument.type
    }

    /**
     * @param \Closure(HtmlScreenshotBuilder): mixed $configure
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRejected(\Closure $configure): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $configure($this->createBuilder(HtmlScreenshotBuilder::class));
    }

    public function testContentFromTemplate(): void
    {
        $this->createBuilder(HtmlScreenshotBuilder::class)->content('content.html.twig', ['name' => 'Shot'])->generate();

        self::assertStringContainsString('<h1>Hello Shot!</h1>', (string) $this->browser->source);
    }

    public function testContentIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->createBuilder(HtmlScreenshotBuilder::class)->generate();
    }

    public function testUrlIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->createBuilder(UrlScreenshotBuilder::class)->generate();
    }
}
