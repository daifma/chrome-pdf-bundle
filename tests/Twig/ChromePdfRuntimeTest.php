<?php

namespace Daif\ChromePdfBundle\Tests\Twig;

use Daif\ChromePdfBundle\Builder\BuilderAssetInterface;
use Daif\ChromePdfBundle\Twig\ChromePdfRuntime;
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
}
