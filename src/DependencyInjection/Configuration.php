<?php

namespace Daif\ChromePdfBundle\DependencyInjection;

use Daif\ChromePdfBundle\NodeBuilder\NodeBuilderInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
                    ->info('Path to the Chrome/Chromium/Edge binary (e.g. "%env(CHROME_PATH)%"). If null, auto-detection is used.')
                    ->defaultNull()
                ->end()
                ->integerNode('timeout')
                    ->info('Timeout in milliseconds applied to navigation, waits, printing and screenshots.')
                    ->min(1000)
                    ->defaultValue(30000)
                ->end()
                ->booleanNode('no_sandbox')
                    ->info('Disables the Chrome sandbox (--no-sandbox). Only needed when running as root (e.g. in some Docker images). Security sensitive.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('ignore_certificate_errors')
                    ->info('Ignores TLS certificate errors when loading URLs and resources. Security sensitive.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('temp_dir')
                    ->info('Directory where conversion temporary files are written. If null, sys_get_temp_dir() is used.')
                    ->defaultNull()
                ->end()
                ->append($this->addPatternListNode('allow_list', 'PCRE patterns (with delimiters, e.g. "#^https://intranet\.example\.com/#"). When not empty, every http(s) request made by Chrome must match one of them.'))
                ->append($this->addPatternListNode('deny_list', 'PCRE patterns (with delimiters). Any http(s) request made by Chrome matching one of them is blocked.'))
                ->arrayNode('browser_options')
                    ->info('Raw options passed to HeadlessChromium BrowserFactory::createBrowser(), merged last.')
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

    private function addPatternListNode(string $name, string $info): NodeDefinition
    {
        $treeBuilder = new TreeBuilder($name);
        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        $node
            ->info($info)
            ->beforeNormalization()->castToArray()->end()
            ->scalarPrototype()
                ->validate()
                    ->ifTrue(static fn (mixed $pattern): bool => !\is_string($pattern) || !self::isValidPattern($pattern))
                    ->then(static function (mixed $pattern): never {
                        throw new \InvalidArgumentException(\sprintf('The pattern %s is not a valid PCRE regular expression (delimiters are required, e.g. "#^https://example\.com/#").', json_encode($pattern)));
                    })
                ->end()
            ->end()
            ->defaultValue([])
        ;

        return $node;
    }

    private static function isValidPattern(string $pattern): bool
    {
        // Env placeholders are resolved at runtime and cannot be validated here.
        if (str_starts_with($pattern, 'env_') || str_contains($pattern, '%env(')) {
            return true;
        }

        set_error_handler(static fn (): bool => true);
        try {
            return false !== preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }
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
