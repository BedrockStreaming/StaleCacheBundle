<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddStaleCacheLifetime implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $cachePoolServices = $container->findTaggedServiceIds('cache.pool');
        $staleCacheServices = $container->findTaggedServiceIds('bedrock_stale_cache.stale_cache');

        foreach ($staleCacheServices as $staleServiceId => $staleTags) {
            $staleService = $container->findDefinition($staleServiceId);
            foreach ($staleTags as $tag) {
                $lifetime = $this->findCachePoolDefaultLifetime($tag['cache_pool'], $cachePoolServices);
                if ($lifetime !== null) {
                    $staleService->addMethodCall('setDefaultLifetime', [$lifetime]);
                }
            }
        }
    }

    /**
     * @param array<string, array<array{
     *     'name'?: string,
     *     'default_lifetime'?: int,
     * }>> $cachePoolServices
     */
    private function findCachePoolDefaultLifetime(string $cachePool, array $cachePoolServices): ?int
    {
        foreach ($cachePoolServices as $tags) {
            foreach ($tags as $tag) {
                if (array_key_exists('name', $tag) && $tag['name'] === $cachePool) {
                    return $tag['default_lifetime'] ?? null;
                }
            }
        }

        return null;
    }
}
