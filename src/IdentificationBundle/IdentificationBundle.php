<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 16:50
 */

namespace IdentificationBundle;


use IdentificationBundle\DependencyInjection\Compiler\IdentificationCallbackHandlerPass;
use IdentificationBundle\DependencyInjection\Compiler\IdentificationHandlerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class IdentificationBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container); // TODO: Change the autogenerated stub

        $container->addCompilerPass(new IdentificationHandlerPass());
        $container->addCompilerPass(new IdentificationCallbackHandlerPass());

    }

}