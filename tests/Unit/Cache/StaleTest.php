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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class StaleTest extends TestCase
{
    use ProphecyTrait;

    private const DEFAULT_MAX_STALE = 1800;
    private ObjectProphecy|CacheInterface $internalCache;
    private ObjectProphecy|EventDispatcherInterface $eventDispatcher;
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
    public function testGetNewItem(mixed $value, callable $callback): void
    {
        $key = uniqid('key_', true);
        $beta = (float) rand(1, 10);
        $initialExpiry = \DateTimeImmutable::createFromFormat('U.u', (string) microtime(true))
            ->modify('+1 hour');
        $cacheItem = new CacheItem();
        $cacheItem->expiresAt($initialExpiry);
        $initialExpiryAsFloat = (float) $initialExpiry->format('U.u');

        $assertCallbackReturnsValue = function (callable $passedCallback) use ($cacheItem, $value) {
            $save = true;

            return $value === $passedCallback($cacheItem, $save);
        };

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::that($assertCallbackReturnsValue), 0, $metadataArgument)
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldNotBeCalled();

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        $metadata = [];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);

        $newExpiry = (\Closure::bind(function (CacheItem $item) {
            return $item->expiry;
        }, null, CacheItem::class))($cacheItem);
        self::assertEquals($initialExpiryAsFloat + self::DEFAULT_MAX_STALE, $newExpiry);
    }

    protected function provideValidCallback(): iterable
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

    public function testGetItemHitWithFallback(): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => throw new UnavailableResourceExceptionMock();
        $beta = (float) rand(1, 10);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            ->willReturn($value);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldBeCalledOnce()
            ->will($callback);

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldBeCalledOnce();

        // Item is in cache, but in stale mode
        $metadata = [ItemInterface::METADATA_EXPIRY => microtime(true) + self::DEFAULT_MAX_STALE / 2];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
    }

    /**
     * @dataProvider provideMetadataNotInStale
     */
    public function testGetItemHitWithoutFallback(array $metadata): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => self::fail('This callback should not be called');
        $beta = (float) rand(1, 10);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            ->willReturn($value);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldNotBeCalled();

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
    }

    protected function provideMetadataNotInStale(): iterable
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
        $this->expectException(UnavailableResourceExceptionMock::class);

        $key = uniqid('key_', true);
        $callback = fn () => throw new UnavailableResourceExceptionMock();
        $beta = (float) rand(1, 10);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            ->will($callback);

        $this->internalCache->get($key, Argument::any(), \INF, $metadataArgument)
            ->shouldNotBeCalled();

        $this->eventDispatcher->dispatch(Argument::that(fn ($event) => $event instanceof StaleCacheUsage))
            ->shouldNotBeCalled();

        $metadata = [];
        $this->testedInstance->get($key, $callback, $beta, $metadata);
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
}
