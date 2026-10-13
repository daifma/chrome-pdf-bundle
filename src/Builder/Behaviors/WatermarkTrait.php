<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * Draws a watermark under the content of the pages. Requires qpdf (text and image watermarks are rendered with Chrome).
 *
 * @package Behavior\\Watermark
 */
trait WatermarkTrait
{
    use AssetBaseDirFormatterAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * The watermark source type. Options: 'text', 'image', 'pdf'.
     *
     * @example watermarkSource(WatermarkSource::Text)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('watermark_source', enumClass: WatermarkSource::class))]
    public function watermarkSource(WatermarkSource $watermarkSource): static
    {
        $this->getBodyBag()->set('watermarkSource', $watermarkSource);

        return $this;
    }

    /**
     * The watermark content. For 'text', the string to render.
     * For 'image' or 'pdf', the filename of the watermark file.
     *
     * @example watermarkExpression('CONFIDENTIAL')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('watermark_expression'))]
    public function watermarkExpression(string $watermarkExpression): static
    {
        $this->getBodyBag()->set('watermarkExpression', $watermarkExpression);

        return $this;
    }

    /**
     * Page ranges to watermark (e.g., '1-3', '5'). Empty string means all pages.
     *
     * @example watermarkPages('1-3')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('watermark_pages'))]
    public function watermarkPages(string|null $watermarkPages = null): static
    {
        if (!$watermarkPages) {
            $this->getBodyBag()->unset('watermarkPages');
        } else {
            ValidatorFactory::range($watermarkPages);
            $this->getBodyBag()->set('watermarkPages', $watermarkPages);
        }

        return $this;
    }

    /**
     * Advanced options. Supported keys: font (CSS font family), points (font size, default 48),
     * color (CSS color, default #808080), opacity (0 to 1, default 0.5), rotation (degrees, default 45),
     * scale (image width relative to the page width, 0 to 1, default 0.5). Other pdfcpu keys are ignored.
     *
     * @param array<string, mixed> $watermarkOptions
     *
     * @example watermarkOptions(['opacity' => 0.5])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('watermark_options', useAttributeAsKey: 'key', prototype: 'variable'))]
    public function watermarkOptions(array $watermarkOptions): static
    {
        $this->getBodyBag()->set('watermarkOptions', $watermarkOptions);

        return $this;
    }

    /**
     * An image or PDF file used as watermark source (required when watermarkSource is 'image' or 'pdf').
     *
     * As asset files, by default the file is fetched in the assets folder
     * of your application. For more information about path resolution go to
     * assets documentation.
     *
     * @example watermarkFile('watermark.pdf')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('watermark_file'))]
    public function watermarkFile(string|\Stringable $path): static
    {
        $path = (string) $path;
        $this->getBodyBag()->set('watermark', [$path => $this->getAssetBaseDirFormatter()->resolve($path)]);

        return $this;
    }
}
