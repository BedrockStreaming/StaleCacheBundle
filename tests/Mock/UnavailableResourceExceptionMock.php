<?php

namespace Bedrock\StaleCacheBundle\Tests\Mock;

use Bedrock\StaleCacheBundle\Exception\UnavailableResourceException;
use Throwable;

class UnavailableResourceExceptionMock extends \Exception implements UnavailableResourceException
{
}