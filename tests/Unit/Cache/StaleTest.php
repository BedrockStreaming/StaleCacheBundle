<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Tests\Unit\Cache;

use Bedrock\StaleCacheBundle\Cache\Stale;
use Bedrock\StaleCacheBundle\Event\StaleCacheUsage;
use Bedrock\StaleCacheBundle\Tests\Mock\UnavailableResourceExceptionMock;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class StaleTest extends TestCase
{
    use ProphecyTrait;

    private const DEFAULT_MAX_STALE = 1800;

    /** @var ObjectProphecy|TagAwareCacheInterface */
    private ObjectProphecy $internalCache;

    /** @var ObjectProphecy|EventDispatcherInterface */
    private ObjectProphecy $eventDispatcher;

    private Stale $testedInstance;

    public function setUp(): void
    {
        $this->internalCache = $this->prophesize(TagAwareCacheInterface::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

        $this->testedInstance = new Stale(
            $this->internalCache->reveal(),
            $this->eventDispatcher->reveal(),
            self::DEFAULT_MAX_STALE
        );
    }

    /**
     * @dataProvider provideValidCallback
     */
    public function testGetNewItem($value, callable $callback): void
    {
        $key = uniqid('key_', true);
        $beta = (float) rand(1, 10);
        $initialExpiry = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true))
            ->modify('+1 hour');
        $cacheItem = new CacheItem();
        $cacheItem->expiresAt($initialExpiry);
        $initialExpiryAsFloat = (float) $initialExpiry->format('U.u');

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Execute $callback
            ->will(function ($args) use ($cacheItem) {
                $save = true;

                return $args[1]($cacheItem, $save);
            })
            ->shouldBeCalledOnce();

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldNotBeCalled();

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        $metadata = [];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
        self::assertCacheItemExpiryEquals($initialExpiryAsFloat + self::DEFAULT_MAX_STALE, $cacheItem);
    }

    /**
     * @dataProvider provideValidCallback
     */
    public function testGetItemHitAndForceRefresh($newValue, callable $callback)
    {
        $key = uniqid('key_', true);
        $oldValue = uniqid('old_value_', true);
        $beta = (float) rand(1, 10);
        $initialExpiry = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true))
            ->modify('+1 hour');
        $cacheItem = new CacheItem();
        $cacheItem->expiresAt($initialExpiry);
        $initialExpiryAsFloat = (float) $initialExpiry->format('U.u');

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Use cached value
            ->willReturn($oldValue);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            // Execute $callback
            ->will(function ($args) use ($cacheItem) {
                $save = true;

                return $args[1]($cacheItem, $save);
            })
            ->shouldBeCalledOnce();

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        // Item is in cache, but in stale mode
        // We force refreshing the value, getting a new one
        $metadata = [ItemInterface::METADATA_EXPIRY => microtime(true) + self::DEFAULT_MAX_STALE / 2];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($newValue, $result);
        self::assertCacheItemExpiryEquals($initialExpiryAsFloat + self::DEFAULT_MAX_STALE, $cacheItem);
    }

    public function provideValidCallback(): iterable
    {
        $value = uniqid('value_', true);

        yield 'basic callback' => [
            'value' => $value,
            'callback' => fn () => $value,
        ];

        yield 'callback using cache item' => [
            'value' => $value,
            'callback' => fn (ItemInterface $item) => $value,
        ];

        yield 'callback using cache item and save boolean' => [
            'value' => $value,
            'callback' => fn (ItemInterface $item, bool & $save) => $value,
        ];
    }

    public function testGetItemHitAndUseStaleMode(): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = function () {
            throw new UnavailableResourceExceptionMock(true);
        };
        $beta = (float) rand(1, 10);
        $initialExpiry = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true))
            ->modify('+1 hour');
        $cacheItem = new CacheItem();
        $cacheItem->expiresAt($initialExpiry);
        $initialExpiryAsFloat = (float) $initialExpiry->format('U.u');

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Use cached value
            ->willReturn($value);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldBeCalledOnce()
            // Execute $callback
            ->will(function ($args) use ($cacheItem) {
                $save = true;

                return $args[1]($cacheItem, $save);
            });

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldBeCalledOnce();

        // Item is in cache, but in stale mode
        // Value cannot be refreshed due to failing source
        $metadata = [ItemInterface::METADATA_EXPIRY => microtime(true) + self::DEFAULT_MAX_STALE / 2];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
        self::assertCacheItemExpiryEquals($initialExpiryAsFloat, $cacheItem);
    }

    /**
     * @dataProvider provideGetItemHitAndFailsToUseStaleMode
     */
    public function testGetItemHitAndFailsToUseStaleMode(callable $callback, string $exceptionClass): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $beta = (float) rand(1, 10);
        $initialExpiry = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true))
            ->modify('+1 hour');
        $cacheItem = new CacheItem();
        $cacheItem->expiresAt($initialExpiry);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Use cached value
            ->willReturn($value);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldBeCalledOnce()
            // Execute $callback
            ->will(function ($args) use ($cacheItem) {
                $save = true;

                return $args[1]($cacheItem, $save);
            });

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        // Item is in cache, but in stale mode
        // Value cannot be refreshed due to failing source
        $metadata = [ItemInterface::METADATA_EXPIRY => microtime(true) + self::DEFAULT_MAX_STALE / 2];
        $this->expectException($exceptionClass);
        $this->testedInstance->get($key, $callback, $beta, $metadata);
    }

    public function provideGetItemHitAndFailsToUseStaleMode(): iterable
    {
        yield 'error do not allow stale cache mode' => [
            'callback' => function () {
                throw new UnavailableResourceExceptionMock(false);
            },
            'exception_class' => UnavailableResourceExceptionMock::class,
        ];

        yield 'error do not implement the correct interface' => [
            'callback' => function () {
                throw new \Exception();
            },
            'exception_class' => \Exception::class,
        ];
    }

    /**
     * @dataProvider provideMetadataNotInStale
     */
    public function testGetItemHitWithoutStaleMode(array $metadata): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => self::fail('The passed callback should not be called');
        $beta = (float) rand(1, 10);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Use cached value
            ->willReturn($value);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldNotBeCalled()
            // Use cached value
            ->willReturn($value); // To avoid type errors if it's actually called

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
    }

    public function provideMetadataNotInStale(): iterable
    {
        yield 'no expiration' => [
            'metadata' => [],
        ];

        yield 'future expiration but not yet stale' => [
            'metadata' => [ItemInterface::METADATA_EXPIRY => microtime(true) + self::DEFAULT_MAX_STALE * 2],
        ];
    }

    public function testGetItemMissWithFailingCallback(): void
    {
        $key = uniqid('key_', true);
        $callback = function () {
            throw new UnavailableResourceExceptionMock(true);
        };
        $beta = (float) rand(1, 10);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Execute $callback
            ->will(function ($args) {
                $save = true;

                return $args[1](new CacheItem(), $save);
            });

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldNotBeCalled();

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        $metadata = [];
        $this->expectException(UnavailableResourceExceptionMock::class);
        $this->testedInstance->get($key, $callback, $beta, $metadata);
    }

    public function testGetItemWithDefaultLifetime()
    {
        $defaultLifetime = rand(100, 200);

        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn (ItemInterface $item) => $value;
        $beta = (float) rand(1, 10);

        $cacheItem = new CacheItem();

        $expectedExpiryMin = microtime(true) + self::DEFAULT_MAX_STALE + $defaultLifetime;

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            // Use cached value
            ->willReturn($value);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldBeCalledOnce()
            // Execute $callback
            ->will(function ($args) use ($cacheItem) {
                $save = true;

                return $args[1]($cacheItem, $save);
            });

        $this->testedInstance->setDefaultLifetime($defaultLifetime);

        // Item is in cache, but in stale mode
        // Value cannot be refreshed due to failing source
        $metadata = [ItemInterface::METADATA_EXPIRY => microtime(true) + self::DEFAULT_MAX_STALE / 2];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);

        $expectedExpiryMax = microtime(true) + self::DEFAULT_MAX_STALE + $defaultLifetime;

        self::assertEquals($value, $result);

        $cacheItemExpiry = self::getCacheItemExpiry($cacheItem);
        self::assertGreaterThan($expectedExpiryMin, $cacheItemExpiry);
        self::assertLessThan($expectedExpiryMax, $cacheItemExpiry);
    }

    public function testDelete(): void
    {
        $key = uniqid('key_', true);
        $success = true;

        $this->internalCache->delete($key)
            ->shouldBeCalledOnce()
            ->willReturn($success);

        $result = $this->testedInstance->delete($key);
        self::assertEquals($success, $result);
    }

    public function testInvalidateTags(): void
    {
        $tags = [uniqid('tag_', true)];
        $success = true;

        $this->internalCache->invalidateTags($tags)
            ->shouldBeCalledOnce()
            ->willReturn($success);

        $result = $this->testedInstance->invalidateTags($tags);
        self::assertEquals($success, $result);
    }

    private static function assertCacheItemExpiryEquals(float $expiry, CacheItem $cacheItem)
    {
        $cacheItemExpiry = (\Closure::bind(function (CacheItem $item) {
            return $item->expiry;
        }, null, CacheItem::class))($cacheItem);
        self::assertEquals($expiry, $cacheItemExpiry);
    }

    private static function getCacheItemExpiry(CacheItem $cacheItem)
    {
        return (\Closure::bind(function (CacheItem $item) {
            return $item->expiry;
        }, null, CacheItem::class))($cacheItem);
    }
}
