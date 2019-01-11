<?php

namespace SubscriptionBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use SubscriptionBundle\DependencyInjection\Compiler\CallbackHandlerPass;
use SubscriptionBundle\DependencyInjection\Compiler\NotificationHandlerPass;
use SubscriptionBundle\DependencyInjection\Compiler\SubscriptionHandlerPass;
use SubscriptionBundle\DependencyInjection\Compiler\UnsubscriptionHandlerPass;

class SubscriptionBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new SubscriptionHandlerPass());
        $container->addCompilerPass(new CallbackHandlerPass());
        $container->addCompilerPass(new UnsubscriptionHandlerPass());
        $container->addCompilerPass(new NotificationHandlerPass());
    }
}