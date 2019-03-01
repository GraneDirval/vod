<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 10.01.19
 * Time: 10:58
 */

namespace IdentificationBundle\Identification\Service;


use App\Utils\UuidGenerator;
use IdentificationBundle\Entity\CarrierInterface;
use IdentificationBundle\Entity\User;
use IdentificationBundle\Identification\DTO\DeviceData;

class UserFactory
{
    public function create(
        string $msisdn, CarrierInterface $carrier, string $ip, string $identificationToken = null, string $processId = null, DeviceData $deviceData = null
    ): User
    {
        $user = new User(UuidGenerator::generate());

        $user->setIdentifier($msisdn);
        $user->setCarrier($carrier);
        $user->setCountry($carrier->getCountryCode());
        $user->setIp($ip);

        if ($processId) {
            $user->setIdentificationProcessId($processId);
        }

        if ($identificationToken) {
            $user->setIdentificationToken($identificationToken);
        }

        if ($deviceData) {
            $user->setDeviceManufacturer($deviceData->getDeviceManufacturer());
            $user->setDeviceModel($deviceData->getDeviceModel());
            $user->setConnectionType($deviceData->getConnectionType());
            $user->setIdentificationUrl($deviceData->getIdentificationUrl());
        }

        return $user;
    }
}