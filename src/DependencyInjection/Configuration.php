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
            ->end();

        return $treeBuilder;
    }
}
