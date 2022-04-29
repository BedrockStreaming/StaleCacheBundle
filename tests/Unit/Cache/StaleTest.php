<?php

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
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class StaleTest extends TestCase
{
   use ProphecyTrait;

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
            1800
        );
    }

    public function testGetNewItem(): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $assertCallbackReturnsValue = function (callable $passedCallback) use ($value) {
            $item = new CacheItem();
            $save = true;

            return $value === $passedCallback($item, $save);
        };

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::that($assertCallbackReturnsValue), Argument::any(), $metadataArgument)
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $metadata = [];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
    }

    public function testGetNewItemWithoutSave(): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $assertCallbackReturnsValue = function (callable $passedCallback) use ($value) {
            $item = new CacheItem();
            $save = false;

            return $value === $passedCallback($item, $save);
        };

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::that($assertCallbackReturnsValue),  Argument::any(), $metadataArgument)
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $metadata = [];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
    }

    public function testGetExistingItem(): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => self::fail('This callback should not be called');
        $beta = (float) rand(1, 10);

        $metadataArgument = Argument::any();
        $this->internalCache->get($key, Argument::any(), 0, $metadataArgument)
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $metadata = [];
        $result = $this->testedInstance->get($key, $callback, $beta, $metadata);
        self::assertEquals($value, $result);
    }

    /**
     * @dataProvider provideSupportedInternalCacheException
     */
    public function testGetStaleItemHit(string $exception): void
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $this->internalCache->get($key, Argument::cetera())
            ->willThrow($exception);

        $this->eventDispatcher->dispatch(Argument::that(fn($event) => $event instanceof StaleCacheUsage))
            ->shouldBeCalledOnce();

        $result = $this->testedInstance->get($key, $callback, $beta);
        self::assertEquals($value, $result);
    }

    /**
     * @dataProvider provideSupportedInternalCacheException
     */
    public function testGetStaleItemMiss(string $exception): void
    {
        $this->expectException($exception);

        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $this->internalCache->get($key, Argument::cetera())
            ->willThrow($exception);

        $this->eventDispatcher->dispatch(Argument::that(fn($event) => $event instanceof StaleCacheUsage))
            ->shouldBeCalledOnce();


        $this->testedInstance->get($key, $callback, $beta);
    }

    public function provideSupportedInternalCacheException(): \Iterator
    {
        yield 'UnavailableResourceException' => [
            'exception_class' => UnavailableResourceExceptionMock::class,
        ];
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
