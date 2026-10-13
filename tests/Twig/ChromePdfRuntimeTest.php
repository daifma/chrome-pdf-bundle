<?php

namespace Daif\ChromePdfBundle\Tests\Twig;

use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Enumeration\Part;
use Daif\ChromePdfBundle\Tests\Twig\Fixtures\ResolvingBuilder;
use Daif\ChromePdfBundle\Twig\ChromePdfRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\AssetMapperRepository;

class ChromePdfRuntimeTest extends TestCase
{
    public function testGetAsset(): void
    {
        $runtime = new ChromePdfRuntime();
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder
            ->expects($this->once())
            ->method('addAsset')
            ->with('foo')
        ;
        $runtime->setBuilder($builder);
        $this->assertSame('foo', $runtime->getAssetUrl('foo'));
    }

    public function testGetAssetThrowsWhenBuilderIsNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The chrome_pdf_asset function must be used in a Chrome PDF context.');
        $runtime = new ChromePdfRuntime();
        $runtime->getAssetUrl('foo');
    }

    public function testGetFontFace(): void
    {
        $runtime = new ChromePdfRuntime();
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder
            ->expects($this->once())
            ->method('addAsset')
            ->with('foo.ttf')
        ;
        $runtime->setBuilder($builder);
        $this->assertSame(
            '@font-face {font-family: "my_font";src: url("foo.ttf");}',
            $runtime->getFontFace('foo.ttf', 'my_font'),
        );
    }

    public function testGetFontStyleTag(): void
    {
        $runtime = new ChromePdfRuntime();
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder
            ->expects($this->once())
            ->method('addAsset')
            ->with('foo.ttf')
        ;
        $runtime->setBuilder($builder);
        $this->assertSame(
            '<style>@font-face {font-family: "my_font";src: url("foo.ttf");}</style>',
            $runtime->getFontStyleTag('foo.ttf', 'my_font'),
        );
    }

    public function testGetAssetUrlWhenAssetMapperRepository(): void
    {
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder->expects($this->once())
            ->method('addAsset')
            ->with('/image/result.png')
        ;

        $assetMapperRepository = $this->createMock(AssetMapperRepository::class);
        $assetMapperRepository->expects($this->once())
            ->method('find')
            ->with('image/origin.png')
            ->willReturn('/image/result.png')
        ;

        $runtime = new ChromePdfRuntime(null, $assetMapperRepository);
        $runtime->setBuilder($builder);

        $path = $runtime->getAssetUrl('image/origin.png');

        $this->assertSame('result.png', $path);
    }

    public function testGetAssetUrlWhenPackages(): void
    {
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder->expects($this->once())
            ->method('addAsset')
            ->with('image/result.png')
        ;

        $packages = $this->createMock(Packages::class);
        $packages->expects($this->once())
            ->method('getUrl')
            ->with('image/origin.png')
            ->willReturn('/image/result.png')
        ;

        $runtime = new ChromePdfRuntime($packages, null);
        $runtime->setBuilder($builder);

        $path = $runtime->getAssetUrl('image/origin.png');

        $this->assertSame('result.png', $path);
    }

    public function testGetAssetUrlWhenAssetMapperRepositoryAndPackages(): void
    {
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder->expects($this->once())
            ->method('addAsset')
            ->with('image/result.png')
        ;

        $packages = $this->createMock(Packages::class);
        $packages->expects($this->once())
            ->method('getUrl')
            ->with('image/origin.png')
            ->willReturn('/image/result.png')
        ;

        $assetMapperRepository = $this->createMock(AssetMapperRepository::class);
        $assetMapperRepository->expects($this->once())
            ->method('find')
            ->with('image/origin.png')
            ->willReturn(null)
        ;

        $runtime = new ChromePdfRuntime($packages, $assetMapperRepository);
        $runtime->setBuilder($builder);

        $path = $runtime->getAssetUrl('image/origin.png');

        $this->assertSame('result.png', $path);
    }

    public function testGetAssetUrlWhenMissingAssetMapperRepositoryAndPackages(): void
    {
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder->expects($this->once())
            ->method('addAsset')
            ->with('image/origin.png')
        ;

        $runtime = new ChromePdfRuntime(null, null);
        $runtime->setBuilder($builder);

        $path = $runtime->getAssetUrl('image/origin.png');

        $this->assertSame('origin.png', $path);
    }

    public function testBodyAssetWithWindowsPathReturnsItsBasename(): void
    {
        $builder = $this->createMock(BuilderAssetInterface::class);
        $builder->expects($this->once())->method('addAsset')->with('C:\\project\\assets\\logo.png');

        $runtime = new ChromePdfRuntime();
        $runtime->setBuilder($builder, Part::Body);

        self::assertSame('logo.png', $runtime->getAssetUrl('C:\\project\\assets\\logo.png'));
    }

    /**
     * @return iterable<string, array{Part}>
     */
    public static function isolatedParts(): iterable
    {
        yield 'header' => [Part::Header];
        yield 'footer' => [Part::Footer];
    }

    #[DataProvider('isolatedParts')]
    public function testHeaderAndFooterAssetsAreInlinedAsDataUris(Part $part): void
    {
        $logo = __DIR__.'/../Fixtures/assets/logo.png';
        $builder = new ResolvingBuilder(['logo.png' => $logo]);

        $runtime = new ChromePdfRuntime();
        $runtime->setBuilder($builder, $part);

        $dataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo));
        self::assertSame($dataUri, $runtime->getAssetUrl('logo.png'));
        self::assertSame('@font-face {font-family: "my_font";src: url("'.$dataUri.'");}', $runtime->getFontFace('logo.png', 'my_font'));
        self::assertSame('<style>@font-face {font-family: "my_font";src: url("'.$dataUri.'");}</style>', $runtime->getFontStyleTag('logo.png', 'my_font'));
        self::assertSame([], $builder->added);
    }

    public function testRemoteAssetInHeaderIsKeptAsUrl(): void
    {
        $runtime = new ChromePdfRuntime();
        $runtime->setBuilder(new ResolvingBuilder(['https://cdn.example.com/logo.png' => 'https://cdn.example.com/logo.png']), Part::Header);

        self::assertSame('https://cdn.example.com/logo.png', $runtime->getAssetUrl('https://cdn.example.com/logo.png'));
    }

    public function testHeaderAssetRequiresAResolvingBuilder(): void
    {
        $runtime = new ChromePdfRuntime();
        $runtime->setBuilder($this->createMock(BuilderAssetInterface::class), Part::Header);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The chrome_pdf_asset function cannot be used in a header or a footer');

        $runtime->getAssetUrl('logo.png');
    }

    public function testUnreadableHeaderAssetThrows(): void
    {
        $runtime = new ChromePdfRuntime();
        $runtime->setBuilder(new ResolvingBuilder(['missing.png' => __DIR__.'/missing.png']), Part::Footer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not read the asset');

        $runtime->getAssetUrl('missing.png');
    }

    public function testPartIsResetWithTheBuilder(): void
    {
        $runtime = new ChromePdfRuntime();
        $runtime->setBuilder($this->createMock(BuilderAssetInterface::class), Part::Header);
        self::assertSame(Part::Header, $runtime->getPart());

        $runtime->setBuilder(null, Part::Header);
        self::assertNull($runtime->getBuilder());
        self::assertNull($runtime->getPart());
    }
}
