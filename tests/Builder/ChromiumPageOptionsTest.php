<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Daif\ChromePdfBundle\Enumeration\EmulatedMediaType;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Page options shared by every builder (PDF and screenshot).
 */
final class ChromiumPageOptionsTest extends AbstractBuilderTestCase
{
    /**
     * @return iterable<string, array{class-string<HtmlPdfBuilder|HtmlScreenshotBuilder>, string}>
     */
    public static function htmlBuilders(): iterable
    {
        yield 'pdf' => [HtmlPdfBuilder::class, 'htmlToPdf'];
        yield 'screenshot' => [HtmlScreenshotBuilder::class, 'htmlToScreenshot'];
    }

    /**
     * @param class-string<HtmlPdfBuilder|HtmlScreenshotBuilder> $class
     */
    #[DataProvider('htmlBuilders')]
    public function testDefaultPageOptions(string $class, string $method): void
    {
        $this->createBuilder($class)->contentRaw('<p>Hello</p>')->generate();

        self::assertSame($method, $this->browser->method);
        self::assertSame('<p>Hello</p>', $this->browser->source);
        self::assertSame(['waitEvent' => 'load'], $this->browser->pageOptions);
    }

    /**
     * @param class-string<HtmlPdfBuilder|HtmlScreenshotBuilder> $class
     */
    #[DataProvider('htmlBuilders')]
    public function testEveryPageOption(string $class): void
    {
        $this->createBuilder($class)
            ->contentRaw('<p>Hello</p>')
            ->cookies([['name' => 'a', 'value' => 'b', 'domain' => 'example.com', 'secure' => true, 'httpOnly' => false, 'sameSite' => 'lax']]) // @phpstan-ignore argument.type
            ->extraHttpHeaders(['X-One' => '1'])
            ->addExtraHttpHeaders(['X-Two' => 2]) // @phpstan-ignore argument.type
            ->userAgent('MyAgent/1.0')
            ->emulatedMediaType(EmulatedMediaType::Screen)
            ->emulatedMediaFeatures([['name' => 'prefers-color-scheme', 'value' => 'dark']])
            ->waitDelay('500ms')
            ->waitForExpression("window.status === 'ready'")
            ->waitForSelector('#ready')
            ->omitBackground()
            ->addAsset('logo.png')
            ->failOnHttpStatusCodes([401, 499, 401])
            ->failOnResourceHttpStatusCodes([599])
            ->ignoreResourceHttpStatusDomains(['https://Sentry-CDN.com:443/path', '*.example.com', ''])
            ->failOnResourceLoadingFailed()
            ->failOnConsoleExceptions()
            ->generate()
        ;

        $options = $this->browser->pageOptions;
        $assets = $options['assets'];
        unset($options['assets']);

        self::assertSame([
            'cookies' => [['name' => 'a', 'value' => 'b', 'domain' => 'example.com', 'secure' => true, 'httpOnly' => false, 'sameSite' => 'Lax']],
            'extraHttpHeaders' => ['X-One' => '1', 'X-Two' => '2'],
            'emulatedMediaFeatures' => [['name' => 'prefers-color-scheme', 'value' => 'dark']],
            'failOnHttpStatusCodes' => [401, 499],
            'failOnResourceHttpStatusCodes' => [599],
            'ignoreResourceHttpStatusDomains' => ['sentry-cdn.com', 'example.com'],
            'userAgent' => 'MyAgent/1.0',
            'waitDelay' => '500ms',
            'waitForExpression' => "window.status === 'ready'",
            'waitForSelector' => '#ready',
            'emulatedMediaType' => 'screen',
            'waitEvent' => 'load',
            'omitBackground' => true,
            'failOnResourceLoadingFailed' => true,
            'failOnConsoleExceptions' => true,
        ], $options);

        self::assertIsArray($assets);
        self::assertCount(1, $assets);
        self::assertInstanceOf(\SplFileInfo::class, $assets['logo.png']);
        self::assertFileEquals(self::FIXTURE_DIR.'/assets/logo.png', $assets['logo.png']->getPathname());
    }

    /**
     * @return iterable<string, array{bool|null, bool|null, string}>
     */
    public static function waitEvents(): iterable
    {
        yield 'default' => [null, null, 'load'];
        yield 'skip both' => [true, true, 'load'];
        yield 'network almost idle' => [null, false, 'networkAlmostIdle'];
        yield 'network idle' => [false, null, 'networkIdle'];
        yield 'network idle wins' => [false, false, 'networkIdle'];
    }

    #[DataProvider('waitEvents')]
    public function testWaitEventDerivation(bool|null $skipNetworkIdle, bool|null $skipNetworkAlmostIdle, string $expected): void
    {
        foreach ([HtmlPdfBuilder::class, HtmlScreenshotBuilder::class] as $class) {
            $builder = $this->createBuilder($class)->contentRaw('x');
            if (null !== $skipNetworkIdle) {
                $builder->skipNetworkIdleEvent($skipNetworkIdle);
            }
            if (null !== $skipNetworkAlmostIdle) {
                $builder->skipNetworkAlmostIdleEvent($skipNetworkAlmostIdle);
            }
            $builder->generate();

            self::assertSame($expected, $this->browser->pageOptions['waitEvent']);
        }
    }

    public function testSymfonyCookiesAreNormalizedIntoArrays(): void
    {
        $this->requestStack->push(Request::create('https://intranet.example.org/page'));

        $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->cookies([
                Cookie::create('session', 'abc', 0, '/app', 'example.com', true, true, false, Cookie::SAMESITE_STRICT),
                ['name' => 'plain', 'value' => 'v', 'domain' => 'example.com'],
            ])
            ->setCookie('nodomain', Cookie::create('nodomain', 'x'))
            ->generate()
        ;

        self::assertSame([
            ['name' => 'session', 'value' => 'abc', 'domain' => 'example.com', 'path' => '/app', 'secure' => true, 'httpOnly' => true, 'sameSite' => 'Strict'],
            ['name' => 'plain', 'value' => 'v', 'domain' => 'example.com'],
            ['name' => 'nodomain', 'value' => 'x', 'domain' => 'intranet.example.org', 'path' => '/', 'secure' => false, 'httpOnly' => true, 'sameSite' => 'Lax'],
        ], $this->browser->pageOptions['cookies']);
    }

    public function testForwardCookie(): void
    {
        $request = Request::create('https://intranet.example.org/page', cookies: ['token' => 'secret']);
        $this->requestStack->push($request);

        $this->createBuilder(HtmlScreenshotBuilder::class)
            ->contentRaw('x')
            ->forwardCookie('token')
            ->forwardCookie('missing')
            ->generate()
        ;

        self::assertSame([['name' => 'token', 'value' => 'secret', 'domain' => 'intranet.example.org']], $this->browser->pageOptions['cookies']);
    }

    public function testSymfonyCookieWithoutDomainAndWithoutRequestIsRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);
        $this->expectExceptionMessage('Cookie "nodomain" has no domain');

        $this->createBuilder(HtmlPdfBuilder::class)->cookies([Cookie::create('nodomain', 'x')]);
    }

    public function testInvalidCookieIsRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->cookies([['name' => 'a', 'value' => 'b']]); // @phpstan-ignore argument.type
    }

    public function testEmptyArraysResetOptions(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->cookies([['name' => 'a', 'value' => 'b', 'domain' => 'example.com']])
            ->cookies([])
            ->extraHttpHeaders(['X' => 'y'])
            ->extraHttpHeaders([])
            ->emulatedMediaFeatures([['name' => 'prefers-color-scheme', 'value' => 'dark']])
            ->emulatedMediaFeatures([])
            ->failOnHttpStatusCodes([404])
            ->failOnHttpStatusCodes([])
            ->generate()
        ;

        self::assertSame(['waitEvent' => 'load'], $this->browser->pageOptions);
    }

    /**
     * @return iterable<string, array{\Closure(HtmlPdfBuilder): mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'waitDelay' => [static fn (HtmlPdfBuilder $b) => $b->waitDelay('5 seconds')];
        yield 'waitForExpression' => [static fn (HtmlPdfBuilder $b) => $b->waitForExpression(' ')];
        yield 'waitForSelector' => [static fn (HtmlPdfBuilder $b) => $b->waitForSelector('')];
        yield 'userAgent' => [static fn (HtmlPdfBuilder $b) => $b->userAgent('')];
        yield 'status code' => [static fn (HtmlPdfBuilder $b) => $b->failOnHttpStatusCodes([42])]; // @phpstan-ignore argument.type
        yield 'resource status code' => [static fn (HtmlPdfBuilder $b) => $b->failOnResourceHttpStatusCodes([600])]; // @phpstan-ignore argument.type
        yield 'media feature' => [static fn (HtmlPdfBuilder $b) => $b->emulatedMediaFeatures([['name' => 'x']])]; // @phpstan-ignore argument.type
        yield 'headers' => [static fn (HtmlPdfBuilder $b) => $b->extraHttpHeaders(['X' => ['array']])]; // @phpstan-ignore argument.type
    }

    /**
     * @param \Closure(HtmlPdfBuilder): mixed $configure
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRejected(\Closure $configure): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $configure($this->createBuilder(HtmlPdfBuilder::class));
    }

    /**
     * @return iterable<string, array{class-string<UrlPdfBuilder|UrlScreenshotBuilder>, string}>
     */
    public static function urlBuilders(): iterable
    {
        yield 'pdf' => [UrlPdfBuilder::class, 'urlToPdf'];
        yield 'screenshot' => [UrlScreenshotBuilder::class, 'urlToScreenshot'];
    }

    /**
     * @param class-string<UrlPdfBuilder|UrlScreenshotBuilder> $class
     */
    #[DataProvider('urlBuilders')]
    public function testUrlBuildersSendTheUrlAndPageOptions(string $class, string $method): void
    {
        $this->createBuilder($class)
            ->url('https://example.com/page')
            ->failOnHttpStatusCodes([404])
            ->generate()
        ;

        self::assertSame($method, $this->browser->method);
        self::assertSame('https://example.com/page', $this->browser->source);
        self::assertSame(['failOnHttpStatusCodes' => [404], 'waitEvent' => 'load'], $this->browser->pageOptions);
    }
}
