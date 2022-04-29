<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Event;

use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Symfony\Contracts\EventDispatcher\Event;

class StaleCacheUsage extends Event
{
    public function __construct(
        private UnavailableResourceException $exception,
        private string $cacheKey
    ) {
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
