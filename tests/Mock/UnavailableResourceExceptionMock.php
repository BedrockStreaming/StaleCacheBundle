<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Tests\Mock;

use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;

class UnavailableResourceExceptionMock extends \Exception implements UnavailableResourceException
{
    private bool $allowStaleCacheUsage;

    public function __construct(bool $allowStaleCacheUsage)
    {
        $this->allowStaleCacheUsage = $allowStaleCacheUsage;
    }

    public function allowStaleCacheUsage(): bool
    {
        return $this->allowStaleCacheUsage;
    }
}
