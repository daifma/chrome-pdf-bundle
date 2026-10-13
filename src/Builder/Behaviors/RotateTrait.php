<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Enumeration\RotateAngle;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * Requires qpdf.
 *
 * @package Behavior\\Rotate
 */
trait RotateTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * The rotation angle.
     *
     * @example rotateAngle(RotateAngle::Rotate90)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('rotate_angle', enumClass: RotateAngle::class))]
    public function rotateAngle(RotateAngle|null $rotateAngle = null): static
    {
        if (null === $rotateAngle) {
            $this->getBodyBag()->unset('rotateAngle');

            return $this;
        }

        $this->getBodyBag()->set('rotateAngle', $rotateAngle);

        return $this;
    }

    /**
     * Page ranges to rotate (e.g., '1-3', '5'). Empty means all pages.
     *
     * @example rotatePages('1-2')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('rotate_pages'))]
    public function rotatePages(string|null $rotatePages = null): static
    {
        if (!$rotatePages) {
            $this->getBodyBag()->unset('rotatePages');
        } else {
            ValidatorFactory::range($rotatePages);
            $this->getBodyBag()->set('rotatePages', $rotatePages);
        }

        return $this;
    }
}
