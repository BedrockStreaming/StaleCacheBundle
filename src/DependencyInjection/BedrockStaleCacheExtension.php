<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\DependencyInjection;

use Bedrock\StaleCacheBundle\Cache\Stale;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class BedrockStaleCacheExtension extends Extension
{
    public const CONFIG_ROOT_KEY = 'bedrock_stale_cache';

    /**
     * @param array<string, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processedConfig = $this->processConfiguration(new Configuration(), $configs);

        foreach ($processedConfig['decorated_cache_pools'] as $id => $options) {
            $this->configureStaleCacheService($container, $id, $options);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function configureStaleCacheService(ContainerBuilder $container, string $id, array $options): void
    {
        $definition = $container->register($id, Stale::class);
        $definition->setArgument('$internalCache', new Reference($options['cache_pool']));
        $definition->setArgument('$maxStale', $options['max_stale']);
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);
        $definition->addTag('bedrock_stale_cache.stale_cache', $options);
    }
}
