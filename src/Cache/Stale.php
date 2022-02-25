<?php

namespace Bedrock\StaleCacheBundle\Cache;

use Bedrock\StaleCacheBundle\Event\StaleCacheUsage;
use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class Stale implements TagAwareCacheInterface
{
    private CacheInterface $staleCache;

    private CacheInterface $internalCache;

    private EventDispatcherInterface $dispatcher;

    public function __construct(
        CacheInterface $staleCache,
        CacheInterface $internalCache,
        EventDispatcherInterface $dispatcher
    ) {
        $this->staleCache = $staleCache;
        $this->internalCache = $internalCache;
        $this->dispatcher = $dispatcher;
    }


    /**
     * @param array<string,mixed>|null $metadata
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null)
    {
        try {
            $callbackWithStaleCache = function (ItemInterface $item, bool &$save) use ($key, $callback) {
                $value = $callback($item, $save);

                if ($save) {
                    // INF force an early recompute
                    $this->staleCache->get($key, fn() => $value, \INF);
                }

                return $value;
            };

            return $this->internalCache->get($key, $callbackWithStaleCache, $beta, $metadata);
        } catch (UnavailableResourceException $exception) {
            $this->dispatcher->dispatch(new StaleCacheUsage($exception));

            // Cache miss should re-throw the exception
            $invalidCallback = function () use ($exception) {
                throw $exception;
            };

            // Beta = 0 disable early recompute
            return $this->staleCache->get($key, $invalidCallback, 0);
        }
    }

    public function delete(string $key): bool
    {
        return $this->internalCache->delete($key);
    }

    public function invalidateTags(array $tags): bool
    {
        if ($this->internalCache instanceof TagAwareCacheInterface) {
            return $this->internalCache->invalidateTags($tags);
        }

        return true;
    }
}
