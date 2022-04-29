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
        dump(__METHOD__);
        $processedConfig = $this->processConfiguration(new Configuration(), $configs);

        foreach ($processedConfig['decorated_services'] as $id => $options) {
            $this->configureStaleCacheService($container, $id, $options);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function configureStaleCacheService(ContainerBuilder $container, string $id, array $options): void
    {
        dump(__METHOD__);
        dump($id);
        dump($options);

        $definition = $container->register($id.'.stale', Stale::class);
        $definition->setArgument('$staleCache', new Reference($id.'.inner_stale'));
        $definition->setArgument('$internalCache', new Reference($id.'.inner_stale'));
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);
        $definition->setDecoratedService($id, $id.'.inner_stale');
    }
}
