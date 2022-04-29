<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Event;

use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Symfony\Contracts\EventDispatcher\Event;

class StaleCacheUsage extends Event
{
    private UnavailableResourceException $exception;

    public function __construct(UnavailableResourceException $exception)
    {
        $this->exception = $exception;
    }

    public function getException(): UnavailableResourceException
    {
        return $this->exception;
    }
}
