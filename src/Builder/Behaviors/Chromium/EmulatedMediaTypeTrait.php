<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Enumeration\EmulatedMediaType;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;

/**
 * @package Behavior\\MediaType
 */
trait EmulatedMediaTypeTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Forces Chromium to emulate, either "screen" or "print". (default "print").
     *
     * @example emulatedMediaType(EmulatedMediaType::Screen)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('emulated_media_type', enumClass: EmulatedMediaType::class))]
    public function emulatedMediaType(EmulatedMediaType $mediaType): static
    {
        $this->getBodyBag()->set('emulatedMediaType', $mediaType);

        return $this;
    }
}
