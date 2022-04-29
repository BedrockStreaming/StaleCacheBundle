<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Cache;

use Bedrock\StaleCacheBundle\Event\StaleCacheUsage;
use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class Stale implements TagAwareCacheInterface
{
    private CacheInterface $internalCache;

    private EventDispatcherInterface $dispatcher;

    private int $maxStale;

    public function __construct(
        CacheInterface $internalCache,
        EventDispatcherInterface $dispatcher,
        int $maxStale
    ) {
        $this->internalCache = $internalCache;
        $this->dispatcher = $dispatcher;
        $this->maxStale = $maxStale;
    }

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        $isHit = true;

        $callbackWithIncreasedCacheTime = function (ItemInterface $item, bool &$save) use ($callback, &$isHit) {
            $value = $callback($item, $save);
            $isHit = false;

            if ($item instanceof CacheItem) {
                $this->increaseCacheLifetime($item);
            }

            return $value;
        };

        // $beta = 0 to disable early recompute
        $value = $this->internalCache->get($key, $callbackWithIncreasedCacheTime, 0, $metadata);

        // If value is cached and we're in stale mode, try to force recomputing it
        // Ignore correctly marked exceptions: this is where the stale mode should work as a fallback
        if ($isHit && $this->isStale($metadata)) {
            try {
                // $beta = \INF to force an early recompute
                $value = $this->internalCache->get($key, $callback, \INF, $metadata);
            } catch (UnavailableResourceException $exception) {
                $this->dispatcher->dispatch(new StaleCacheUsage($exception));
            }
        }

        return $value;
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

        return false;
    }

    private function increaseCacheLifetime(CacheItem $item): void
    {
        // Please to not judge me, this kind of dark magic comes straight out of Symfony
        $callback = \Closure::bind(static function (CacheItem $item, int $maxStale) {
            if (isset($item->expiry) && $item->expiry !== null) {
                $item->expiry += $maxStale;
            }
        }, null, CacheItem::class);

        $callback($item, $this->maxStale);
    }

    /**
     * @param ?array{"expiry"?: ?int} $metadata
     */
    private function isStale(?array $metadata): bool
    {
        $currentExpiry = $metadata[ItemInterface::METADATA_EXPIRY] ?? null;
        if ($currentExpiry === null) {
            return false;
        }

        return ($currentExpiry - $this->maxStale) < microtime(true);
    }
}
