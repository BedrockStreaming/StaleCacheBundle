<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Event;

use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Symfony\Contracts\EventDispatcher\Event;

class StaleCacheUsage extends Event
{
    private UnavailableResourceException $exception;
    private string $cacheKey;

    public function __construct(UnavailableResourceException $exception, string $cacheKey)
    {
        $this->exception = $exception;
        $this->cacheKey = $cacheKey;
    }

    public function getException(): UnavailableResourceException
    {
        return $this->exception;
    }

    public function getCacheKey(): string
    {
        return $this->cacheKey;
    }
}
