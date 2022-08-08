<?php

declare(strict_types=1);

namespace Bedrock\StaleCacheBundle;

use Bedrock\StaleCacheBundle\DependencyInjection\AddStaleCacheLifetime;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BedrockStaleCacheBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddStaleCacheLifetime());
    }
}
