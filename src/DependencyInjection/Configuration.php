<?php

declare(strict_types=1);

namespace PhpQuality\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('php_quality');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('default_lang')->defaultValue('en')->end()

                ->arrayNode('thresholds')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('ccn')
                            ->defaultNull()
                            ->min(1)
                            ->info('Maximum cyclomatic complexity threshold (null = use framework default)')
                        ->end()
                        ->floatNode('lcom')
                            ->defaultNull()
                            ->min(0)
                            ->max(1)
                            ->info('Maximum lack of cohesion threshold (null = use framework default)')
                        ->end()
                        ->integerNode('mi')
                            ->defaultNull()
                            ->min(0)
                            ->max(100)
                            ->info('Minimum maintainability index threshold (null = use framework default)')
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('profiler')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable PHP Quality integration with Symfony Profiler')
                        ->end()
                        ->arrayNode('exclude_paths')
                            ->scalarPrototype()->end()
                            ->defaultValue(['vendor/', 'var/', 'cache/', 'tests/'])
                            ->info('Paths to exclude from profiler analysis')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
