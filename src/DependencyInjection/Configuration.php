<?php

declare(strict_types=1);

namespace ApiKit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * ApiKit bundle configuration.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('api_kit');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('response')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('format')
                            ->values(['json', 'xml'])
                            ->defaultValue('json')
                            ->info('API response format')
                        ->end()
                        ->booleanNode('include_timestamp')
                            ->defaultTrue()
                            ->info('Include timestamp in responses')
                        ->end()
                        ->booleanNode('pretty_print')
                            ->defaultValue('%kernel.debug%')
                            ->info('Format JSON with indentation')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('validation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('translate_messages')
                            ->defaultTrue()
                            ->info('Translate validation messages')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('exception_handling')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('log_errors')
                            ->defaultTrue()
                            ->info('Log errors')
                        ->end()
                        ->booleanNode('show_trace')
                            ->defaultValue('%kernel.debug%')
                            ->info('Show stack trace in responses')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
