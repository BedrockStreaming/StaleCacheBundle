<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Cache;

use Bedrock\StaleCacheBundle\Event\StaleCacheUsage;
use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class Stale implements TagAwareCacheInterface
{
    private CacheInterface $internalCache;

    private EventDispatcherInterface $dispatcher;

    private int $maxStale;

    private ?LoggerInterface $logger;

    private ?int $defaultLifetime = null;

    public function __construct(
        CacheInterface $internalCache,
        EventDispatcherInterface $dispatcher,
        int $maxStale,
        ?LoggerInterface $logger = null
    ) {
        $this->internalCache = $internalCache;
        $this->dispatcher = $dispatcher;
        $this->maxStale = $maxStale;
        $this->logger = $logger;
    }

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null)
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

        $recompute = false;
        if ($this->isStale($metadata)) {
            $this->logDebugMessage('Value is stale, try to recompute it', $key);
            $recompute = true;
        }

        if (!$recompute && $this->shouldTriggerEarlyCacheExpiry($metadata, $beta)) {
            $this->logDebugMessage('Value elected to early expiration, try to recompute it', $key);
            $recompute = true;
        }

        if ($isHit && $recompute) {
            try {
                // $beta = \INF to force an early recompute
                $value = $this->internalCache->get($key, $callbackWithIncreasedCacheTime, \INF, $metadata);
            } catch (UnavailableResourceException $exception) {
                if (!$exception->allowStaleCacheUsage()) {
                    $this->logDebugMessage('Cannot fallback to stale mode', $key, $exception);
                    throw $exception;
                }

                $this->logDebugMessage('Fallback to stale mode', $key, $exception);
                $this->dispatcher->dispatch(new StaleCacheUsage($exception, $key));
            } catch (\Throwable $throwable) {
                $this->logDebugMessage(
                    sprintf('Exception %s do not allow stale cache, it will be rethrown', get_class($throwable)),
                    $key, $throwable
                );

                throw $throwable;
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

    public function setDefaultLifetime(int $defaultLifetime): void
    {
        $this->defaultLifetime = $defaultLifetime;
    }

    private function increaseCacheLifetime(CacheItem $item): void
    {
        $defaultLifetime = $this->defaultLifetime;
        // Please to not judge me, this kind of dark magic comes straight out of Symfony
        $callback = \Closure::bind(static function (CacheItem $item, int $maxStale) use ($defaultLifetime) {
            // Default lifetime is not included in CacheItem
            // See https://github.com/symfony/cache/blob/5cf8e75f02932818889e0609380b8d5427a6c86c/Adapter/ChainAdapter.php#L78-L82
            // for native behavior
            if (isset($item->expiry) && $item->expiry !== null) {
                $item->expiry += $maxStale;
            } elseif ($defaultLifetime !== null) {
                $item->expiresAfter($defaultLifetime + $maxStale);
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

    /**
     * @param ?array{"expiry"?: ?int, "ctime"?: ?int} $metadata
     */
    private function shouldTriggerEarlyCacheExpiry(?array $metadata, ?float $beta): bool
    {
        $expiry = $metadata[ItemInterface::METADATA_EXPIRY] ?? null;
        $ctime = $metadata[ItemInterface::METADATA_CTIME] ?? null;

        if ($expiry === null || $ctime === null) {
            return false;
        }

        // See https://github.com/symfony/cache-contracts/blob/aa79ac322ca42cfed7d744cb55777b9425a93d2d/CacheTrait.php#L58
        // The random part is between about -19 and 0 (averaging around -1),
        // ctime should not be that big (a few hundreds of ms, depending on time to compute the item),
        // so with beta = 1,
        // it should be triggered a few hundreds of ms before due time.
        return ($expiry - $this->maxStale) <= microtime(true) - $ctime / 1000 * $beta * log(random_int(1, \PHP_INT_MAX) / \PHP_INT_MAX);
    }

    private function logDebugMessage(string $message, string $cacheKey, ?\Throwable $throwable = null): void
    {
        if ($this->logger) {
            $this->logger->debug($message, ['cache_key' => $cacheKey, 'exception' => $throwable]);
        }
    }
}
