<?php

namespace Bedrock\StaleCacheBundle;

use Bedrock\StaleCacheBundle\DependencyInjection\AddStaleCacheDecoration2;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BedrockStaleCacheBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        //$container->addCompilerPass(new AddStaleCacheDecoration());
    }
}