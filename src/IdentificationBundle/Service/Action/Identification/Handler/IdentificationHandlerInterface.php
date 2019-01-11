<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 18:39
 */

namespace IdentificationBundle\Service\Action\Identification\Handler;


use IdentificationBundle\Entity\CarrierInterface;

interface IdentificationHandlerInterface
{
    public function canHandle(CarrierInterface $carrier): bool;

}