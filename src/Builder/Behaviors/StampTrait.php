<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\Behaviors\Dependencies\AssetBaseDirFormatterAwareTrait;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Enumeration\StampSource;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * Draws a stamp over the pages. Requires qpdf (text and image stamps are rendered with Chrome).
 *
 * @package Behavior\\Stamp
 */
trait StampTrait
{
    use AssetBaseDirFormatterAwareTrait;

    abstract protected function getBodyBag(): BodyBag;

    /**
     * The stamp source type. Options: 'text', 'image', 'pdf'.
     *
     * @example stampSource(StampSource::Text)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('stamp_source', enumClass: StampSource::class))]
    public function stampSource(StampSource $stampSource): static
    {
        $this->getBodyBag()->set('stampSource', $stampSource);

        return $this;
    }

    /**
     * The stamp content. For 'text', the string to render.
     * For 'image' or 'pdf', the filename of the stamp file.
     *
     * @example stampExpression('APPROVED')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('stamp_expression'))]
    public function stampExpression(string $stampExpression): static
    {
        $this->getBodyBag()->set('stampExpression', $stampExpression);

        return $this;
    }

    /**
     * Page ranges to stamp (e.g., '1-3', '5'). Empty string means all pages.
     *
     * @example stampPages('1-3')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('stamp_pages'))]
    public function stampPages(string|null $stampPages = null): static
    {
        if (!$stampPages) {
            $this->getBodyBag()->unset('stampPages');
        } else {
            ValidatorFactory::range($stampPages);
            $this->getBodyBag()->set('stampPages', $stampPages);
        }

        return $this;
    }

    /**
     * Advanced options. Supported keys: font (CSS font family), points (font size, default 48),
     * color (CSS color, default #808080), opacity (0 to 1, default 1), rotation (degrees, default 0),
     * scale (image width relative to the page width, 0 to 1, default 0.5). Other pdfcpu keys are ignored.
     *
     * @param array<string, mixed> $stampOptions
     *
     * @example stampOptions(['opacity' => 0.5])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('stamp_options', useAttributeAsKey: 'key', prototype: 'variable'))]
    public function stampOptions(array $stampOptions): static
    {
        $this->getBodyBag()->set('stampOptions', $stampOptions);

        return $this;
    }

    /**
     * An image or PDF file used as stamp source (required when stampSource is 'image' or 'pdf').
     *
     * As asset files, by default the file is fetched in the assets folder
     * of your application. For more information about path resolution go to
     * assets documentation.
     *
     * @example stampFile('stamp.pdf')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('stamp_file'))]
    public function stampFile(string|\Stringable $path): static
    {
        $path = (string) $path;
        $this->getBodyBag()->set('stamp', [$path => $this->getAssetBaseDirFormatter()->resolve($path)]);

        return $this;
    }
}
