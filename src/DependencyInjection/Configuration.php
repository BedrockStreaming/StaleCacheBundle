<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const CONFIG_ROOT_KEY = 'bedrock_stale_cache';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::CONFIG_ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
            ->arrayNode('decorated_cache_pools')
                ->useAttributeAsKey('stale_service_id')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('cache_pool')->isRequired()->cannotBeEmpty()->end()
                        ->integerNode('max_stale')->isRequired()->end()
                        ->booleanNode('enable_debug_logs')->defaultFalse()->end()
                    ->end()
                ->end();

        return $treeBuilder;
    }
}
