<?php

namespace Daif\ChromePdfBundle\DependencyInjection;

use Daif\ChromePdfBundle\NodeBuilder\NodeBuilderInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @param array<string, array<string, array<array-key, NodeBuilderInterface>>> $builders
     */
    public function __construct(
        private readonly array $builders,
    ) {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('daif_chrome_pdf');

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('assets_directory')
                    ->normalizeKeys(false)
                    ->prototype('scalar')->end()
                    ->info('Base directory will be used for assets, files, markdown')
                    ->defaultValue(['%kernel.project_dir%/assets'])
                    ->beforeNormalization()->castToArray()->end()
                ->end()
                ->scalarNode('chrome_binary')
                    ->info('Path to Chrome/Chromium binary. If null, auto-detection is used.')
                    ->defaultNull()
                ->end()
                ->arrayNode('browser_options')
                    ->info('Options passed to HeadlessChromium BrowserFactory::createBrowser()')
                    ->normalizeKeys(false)
                    ->prototype('variable')->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('request_context')
                    ->info('Override the request context for route URL generation.')
                    ->children()
                        ->scalarNode('base_uri')
                            ->info('Used only when using `->route()`. Overrides the guessed `base_url` from the request. May be useful in CLI.')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('controller_listener')
                    ->defaultTrue()
                    ->info('Enables the listener on kernel.view to stream ChromePdfFileResult object.')
                ->end()
                ->append($this->addDefaultOptionsNode())
            ->end()
        ;

        return $treeBuilder;
    }

    private function addDefaultOptionsNode(): NodeDefinition
    {
        $defaultOptionsTreeBuilder = new TreeBuilder('default_options');
        $defaultOptionsTreeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
        ;

        foreach ($this->builders as $type => $innerBuilders) {
            $typeTreeBuilder = new TreeBuilder($type);
            $typeTreeBuilder->getRootNode()
                ->addDefaultsIfNotSet()
            ;

            foreach ($innerBuilders as $builderType => $builderNodes) {
                $builderTypeTreeBuilder = new TreeBuilder($builderType);
                $builderTypeTreeBuilder->getRootNode()
                    ->addDefaultsIfNotSet()
                ;
                foreach ($builderNodes as $node) {
                    $builderTypeTreeBuilder->getRootNode()->append($node->create());
                }

                $typeTreeBuilder->getRootNode()->append($builderTypeTreeBuilder->getRootNode());
            }

            $defaultOptionsTreeBuilder->getRootNode()->append($typeTreeBuilder->getRootNode());
        }

        return $defaultOptionsTreeBuilder->getRootNode();
    }
}
