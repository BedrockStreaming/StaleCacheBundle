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
            // TODO declare subkeys
            ->variablePrototype();

        return $treeBuilder;
    }
}
