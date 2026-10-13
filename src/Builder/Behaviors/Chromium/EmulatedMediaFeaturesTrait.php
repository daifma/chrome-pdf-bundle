<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors\Chromium;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\NodeBuilder\ArrayNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * @package Behavior\\EmulatedMediaFeatures
 */
trait EmulatedMediaFeaturesTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * You can simulate specific browser conditions by overriding CSS media features.
     * This is particularly useful for forcing "Dark Mode" or testing layouts with reduced motion.
     * (overrides any previous configuration).
     *
     * @param list<array{name: string, value: string}> $emulatedMediaFeatures
     *
     * @example emulatedMediaFeatures([['name' => 'prefers-color-scheme', 'value' => 'dark'], ['name' => 'prefers-reduced-motion', 'value' => 'reduce']])
     */
    #[WithConfigurationNode(new ArrayNodeBuilder('emulated_media_features', prototype: 'array', children: [
        new ScalarNodeBuilder('name', required: true, restrictTo: 'string'),
        new ScalarNodeBuilder('value', required: true, restrictTo: 'string'),
    ]))]
    public function emulatedMediaFeatures(array $emulatedMediaFeatures): static
    {
        if ([] === $emulatedMediaFeatures) {
            $this->getBodyBag()->unset('emulatedMediaFeatures');

            return $this;
        }

        ValidatorFactory::emulatedMediaFeatures($emulatedMediaFeatures);

        $features = [];
        foreach ($emulatedMediaFeatures as $feature) {
            $features[] = ['name' => $feature['name'], 'value' => $feature['value']];
        }

        $this->getBodyBag()->set('emulatedMediaFeatures', $features);

        return $this;
    }
}
