<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Exception;

interface UnavailableResourceException extends \Throwable
{
    /**
     * Returns true if the exception can be catched to fallback on stale mode
     */
    public function allowStaleCacheUsage(): bool;
}
