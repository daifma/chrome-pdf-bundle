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
                ->variableNode('chrome_binary')
                    ->info('Path to the Chrome/Chromium/Edge binary (e.g. "%env(CHROME_PATH)%"). If null, auto-detection is used.')
                    ->defaultNull()
                    ->beforeNormalization()->ifString()->then(static fn (string $v): string|null => '' === $v ? null : $v)->end()
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
                ->variableNode('temp_dir')
                    ->info('Directory where conversion temporary files are written. If null, sys_get_temp_dir() is used.')
                    ->defaultNull()
                    ->beforeNormalization()->ifString()->then(static fn (string $v): string|null => '' === $v ? null : $v)->end()
                ->end()
                ->variableNode('qpdf_binary')
                    ->info('Path to the qpdf binary (e.g. "%env(QPDF_PATH)%"), used to merge, split, rotate, flatten, encrypt, embed, stamp and watermark PDFs. If null, auto-detection is used.')
                    ->defaultNull()
                    ->beforeNormalization()->ifString()->then(static fn (string $v): string|null => '' === $v ? null : $v)->end()
                ->end()
                ->variableNode('exiftool_binary')
                    ->info('Path to the exiftool binary (e.g. "%env(EXIFTOOL_PATH)%"), used to write PDF metadata. If null, auto-detection is used.')
                    ->defaultNull()
                    ->beforeNormalization()->ifString()->then(static fn (string $v): string|null => '' === $v ? null : $v)->end()
                ->end()
                ->variableNode('libreoffice_binary')
                    ->info('Path to the LibreOffice "soffice" binary (e.g. "%env(LIBREOFFICE_PATH)%"), used to convert office documents and to produce PDF/A and PDF/UA. If null, auto-detection is used.')
                    ->defaultNull()
                    ->beforeNormalization()->ifString()->then(static fn (string $v): string|null => '' === $v ? null : $v)->end()
                ->end()
                ->integerNode('engine_timeout')
                    ->info('Timeout in milliseconds of each qpdf, exiftool and LibreOffice call.')
                    ->min(1000)
                    ->defaultValue(120000)
                ->end()
                ->append($this->addPatternListNode('allow_list', 'PCRE patterns (with delimiters, e.g. "#^https://intranet\.example\.com/#"). When not empty, every http(s) request made by Chrome must match one of them.'))
                ->append($this->addPatternListNode('deny_list', 'PCRE patterns (with delimiters). Any http(s) request made by Chrome matching one of them is blocked.'))
                ->arrayNode('local_file_roots')
                    ->info('Directories whose files HTML conversions may load through file:// URLs (e.g. "%kernel.project_dir%/public"), in addition to the conversion temporary directory. Empty by default: keep it limited to public assets.')
                    ->beforeNormalization()->castToArray()->end()
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
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
                ->arrayNode('async')
                    ->info('Asynchronous generation with generateAsync(): a Messenger worker generates the file and sends it to the webhook URL. Requires symfony/messenger and symfony/http-client.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('bus')
                            ->info('Service id of the Messenger bus the generation messages are dispatched on.')
                            ->defaultValue('messenger.default_bus')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->append($this->addNamedWebhookDefinition())
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

        $webhookNode = (new TreeBuilder('webhook', 'scalar'))
            ->getRootNode()
            ->info('Webhook configuration name used by generateAsync() when the builder does not define one.')
        ;

        $defaultOptionsTreeBuilder->getRootNode()->append($webhookNode);

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

    private function addNamedWebhookDefinition(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('webhook');

        return $treeBuilder->getRootNode()
            ->info('Named webhook configurations, used by generateAsync() through webhookConfiguration($name) or default_options.')
            ->defaultValue([])
            ->useAttributeAsKey('name')
            ->arrayPrototype()
                ->children()
                    ->scalarNode('name')
                        ->validate()
                            ->ifTrue(static function (mixed $option): bool {
                                return !\is_string($option);
                            })
                            ->thenInvalid('Invalid webhook configuration name %s')
                        ->end()
                    ->end()
                    ->append($this->addWebhookConfigurationNode('success', true))
                    ->append($this->addWebhookConfigurationNode('error', true))
                    ->append($this->addWebhookConfigurationNode('events', false))
                    ->append($this->addExtraHttpHeadersNode())
                ->end()
                ->validate()
                    ->ifTrue(static function (mixed $option): bool {
                        return !isset($option['success']);
                    })
                    ->thenInvalid('Invalid webhook configuration : At least a "success" key is required.')
                ->end()
                ->validate()
                    ->ifTrue(static function (mixed $option): bool {
                        foreach (['success', 'error', 'events'] as $type) {
                            if (isset($option[$type]) && isset($option[$type]['url']) === isset($option[$type]['route'])) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->thenInvalid('Invalid webhook configuration : Each of "success", "error" and "events" requires either a "url" or a "route" key.')
                ->end()
            ->end()
        ;
    }

    private function addWebhookConfigurationNode(string $name, bool $withMethod): NodeDefinition
    {
        $treeBuilder = new TreeBuilder($name);

        $children = $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('url')
                    ->info('The URL to call.')
                    ->example('https://webhook.site/#!/view/{some-token}')
                ->end()
                ->variableNode('route')
                    ->info('Route configuration.')
                    ->beforeNormalization()
                        ->ifArray()
                            ->then(static function (array $v): array {
                                return [$v[0] ?? null, $v[1] ?? []];
                            })
                        ->ifString()
                            ->then(static function (string $v): array {
                                return [$v, []];
                            })
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($v): bool {
                            return !\is_array($v) || \count($v) !== 2 || !\is_string($v[0]) || !\is_array($v[1]);
                        })
                        ->thenInvalid('The "route" parameter must be a string or an array containing a string and an array.')
                    ->end()
                    ->example("['my_route', ['param1' => 'value1', 'param2' => 'value2']]")
                ->end()
        ;

        if ($withMethod) {
            $children
                ->enumNode('method')
                    ->info('HTTP method to use on that endpoint.')
                    ->values(['POST', 'PUT', 'PATCH'])
                    ->defaultNull()
                ->end()
            ;
        }

        return $children->end();
    }

    private function addExtraHttpHeadersNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder('extra_http_headers');

        return $treeBuilder->getRootNode()
            ->info('HTTP headers sent with the webhook requests (success and error).')
            ->defaultValue([])
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->variablePrototype()
            ->end()
        ;
    }
}
