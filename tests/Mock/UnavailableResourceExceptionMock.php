<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle\Tests\Mock;

use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;

class UnavailableResourceExceptionMock extends \Exception implements UnavailableResourceException
{
}
