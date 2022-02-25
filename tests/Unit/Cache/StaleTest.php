<?php

namespace Bedrock\StaleCacheBundle\Tests\Unit\Cache;

use Bedrock\StaleCacheBundle\Cache\Stale;
use Bedrock\StaleCacheBundle\Event\StaleCacheUsage;
use Bedrock\StaleCacheBundle\Tests\Mock\UnavailableResourceExceptionMock;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class StaleTest extends TestCase
{
    private $staleCache;
    private $internalCache;
    private $eventDispatcher;
    private Stale $testedInstance;

    public function setUp(): void
    {
        $this->staleCache = $this->prophesize(TagAwareCacheInterface::class);
        $this->internalCache = $this->prophesize(TagAwareCacheInterface::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);

        $this->testedInstance = new Stale(
            $this->staleCache->reveal(),
            $this->internalCache->reveal(),
            $this->eventDispatcher->reveal()
        );
    }

    public function testGetNewItem()
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $assertCallbackReturnsValue = function (callable $passedCallback) use ($value) {
            $item = $this->prophesize(ItemInterface::class);
            $save = true;

            return $value === $passedCallback($item->reveal(), $save);
        };

        $this->internalCache->get($key, Argument::that($assertCallbackReturnsValue), $beta, Argument::any())
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $this->staleCache->get($key, Argument::that($assertCallbackReturnsValue), \INF, Argument::any())
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $result = $this->testedInstance->get($key, $callback, $beta);
        self::assertEquals($value, $result);
    }

    public function testGetNewItemWithoutSave()
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $assertCallbackReturnsValue = function (callable $passedCallback) use ($value) {
            $item = $this->prophesize(ItemInterface::class);
            $save = false;

            return $value === $passedCallback($item->reveal(), $save);
        };

        $this->internalCache->get($key, Argument::that($assertCallbackReturnsValue), $beta, Argument::any())
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $this->staleCache->get($key, Argument::that($assertCallbackReturnsValue), \INF, Argument::any())
            ->willReturn($value)
            ->shouldNotBeCalled();

        $result = $this->testedInstance->get($key, $callback, $beta);
        self::assertEquals($value, $result);
    }

    public function testGetExistingItem()
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $this->internalCache->get($key, Argument::any(), $beta, Argument::any())
            ->willReturn($value);

        $this->staleCache->get($key, Argument::cetera())
            ->shouldNotBeCalled();

        $result = $this->testedInstance->get($key, $callback, $beta);
        self::assertEquals($value, $result);
    }

    /**
     * @dataProvider provideSupportedInternalCacheException
     */
    public function testGetStaleItemHit($exception)
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $callback = fn () => $value;
        $beta = (float) rand(1, 10);

        $this->internalCache->get($key, Argument::cetera())
            ->willThrow($exception);

        $this->eventDispatcher->dispatch(Argument::that(fn($event) => $event instanceof StaleCacheUsage))
            ->shouldBeCalledOnce();

        $this->staleCache->get($key, Argument::any(), 0, Argument::any())
            ->willReturn($value)
            ->shouldBeCalledOnce();

        $result = $this->testedInstance->get($key, $callback, $beta);
        self::assertEquals($value, $result);
    }

    /**
     * @dataProvider provideSupportedInternalCacheException
     */
    public function testGetStaleItemMiss($exception)
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

        $this->staleCache->get($key, Argument::any(), 0, Argument::any())
            ->shouldBeCalledOnce()
            ->willThrow($exception);

        $this->testedInstance->get($key, $callback, $beta);
    }

    public function provideSupportedInternalCacheException(): \Iterator
    {
        yield 'UnavailableResourceException' => [
            'exception_class' => UnavailableResourceExceptionMock::class,
        ];
    }

    public function testDelete()
    {
        $key = uniqid('key_', true);
        $success = true;

        $this->internalCache->delete($key)
            ->shouldBeCalledOnce()
            ->willReturn($success);

        $result = $this->testedInstance->delete($key);
        self::assertEquals($success, $result);
    }

    public function testInvalidateTags()
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
