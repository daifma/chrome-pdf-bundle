<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Enumeration\ScreenshotFormat;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\FloatNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\IntegerNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;

/**
 * @package Behavior\\Chromium\\PageProperties
 */
trait ScreenshotPagePropertiesTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * The device screen width in pixels. (Default 800).
     *
     * @example width(600)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('width'))]
    public function width(int $width): static
    {
        ValidatorFactory::positive('width', $width);
        $this->getBodyBag()->set('width', $width);

        return $this;
    }

    /**
     * The device screen height in pixels. (Default 600).
     *
     * @example height(600)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('height'))]
    public function height(int $height): static
    {
        ValidatorFactory::positive('height', $height);
        $this->getBodyBag()->set('height', $height);

        return $this;
    }

    /**
     * Define whether to clip the screenshot according to the device dimensions (viewport only).
     * When false, the full page is captured. (Default false).
     *
     * @example clip() // is same as `->clip(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('clip'))]
    public function clip(bool $bool = true): static
    {
        $this->getBodyBag()->set('clip', $bool);

        return $this;
    }

    /**
     * The device scale ratio, controlling the screenshot pixel density. Set to 2 for retina-quality output. (Default 1).
     *
     * @example deviceScaleFactor(2)
     */
    #[WithConfigurationNode(new FloatNodeBuilder('device_scale_factor'))]
    public function deviceScaleFactor(float $deviceScaleFactor): static
    {
        ValidatorFactory::positive('deviceScaleFactor', $deviceScaleFactor);
        $this->getBodyBag()->set('deviceScaleFactor', $deviceScaleFactor);

        return $this;
    }

    /**
     * The image compression format, either "png", "jpeg" or "webp". (default png).
     *
     * @example format(ScreenshotFormat::Webp)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('format', enumClass: ScreenshotFormat::class))]
    public function format(ScreenshotFormat $format): static
    {
        $this->getBodyBag()->set('format', $format);

        return $this;
    }

    /**
     * The compression quality from range 0 to 100 (jpeg and webp only, ignored for png). (default: Chrome's encoder default).
     *
     * @param int<0, 100> $quality
     *
     * @example quality(50)
     */
    #[WithConfigurationNode(new IntegerNodeBuilder('quality', min: 0, max: 100))]
    public function quality(int $quality): static
    {
        ValidatorFactory::quality($quality);
        $this->getBodyBag()->set('quality', $quality);

        return $this;
    }

    /**
     * Hides default white background and allows generating screenshot with transparency.
     *
     * @example omitBackground() // is same as `->omitBackground(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('omit_background'))]
    public function omitBackground(bool $bool = true): static
    {
        $this->getBodyBag()->set('omitBackground', $bool);

        return $this;
    }

    /**
     * Define whether to optimize image encoding for speed, not for resulting size. (Default false).
     *
     * @example optimizeForSpeed() // is same as `->optimizeForSpeed(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('optimize_for_speed'))]
    public function optimizeForSpeed(bool $bool = true): static
    {
        $this->getBodyBag()->set('optimizeForSpeed', $bool);

        return $this;
    }

    /**
     * MIME type of the generated screenshot, used for the Content-Type of the response.
     */
    protected function getContentType(): string
    {
        $format = $this->getBodyBag()->get('format');

        return ($format instanceof ScreenshotFormat ? $format : ScreenshotFormat::Png)->mimeType();
    }
}
