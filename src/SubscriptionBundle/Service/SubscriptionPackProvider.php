<?php

namespace SubscriptionBundle\Service;


use App\Domain\Entity\Carrier;
use IdentificationBundle\Entity\CarrierInterface;
use IdentificationBundle\Entity\User;
use SubscriptionBundle\Entity\SubscriptionPack;
use SubscriptionBundle\Exception\ActiveSubscriptionPackNotFound;
use SubscriptionBundle\Repository\SubscriptionPackRepository;

class SubscriptionPackProvider
{
    /**
     * @var SubscriptionPackRepository
     */
    private $subscriptionPackRepository;

    /**
     * SubscriptionPackProvider constructor.
     * @param SubscriptionPackRepository $subscriptionPackRepository
     */
    public function __construct(SubscriptionPackRepository $subscriptionPackRepository)
    {
        $this->subscriptionPackRepository = $subscriptionPackRepository;
    }

    /**
     * @param $user
     * @return SubscriptionPack
     * @throws ActiveSubscriptionPackNotFound
     */
    public function getActiveSubscriptionPack(User $user): SubscriptionPack
    {
        /** @var SubscriptionPack $subscriptionPack */
        $subscriptionPack = $this->subscriptionPackRepository->findOneBy([
            'carrier' => $user->getCarrier(),
            'status'    => SubscriptionPack::ACTIVE_SUBSCRIPTION_PACK
        ]);

        if (!$subscriptionPack) {
            throw new ActiveSubscriptionPackNotFound(
                "Active subscription pack not found for carrier {$user->getCarrier()}"
            );
        }
        return $subscriptionPack;
    }


    /**
     * API Created for returning active subscription pack from carrier
     * @param CarrierInterface $carrier
     * @return null| SubscriptionPack
     */
    public function getActiveSubscriptionPackFromCarrier(CarrierInterface $carrier = null)
    {
        if (!$carrier) {
            return null;
        }

        /** @var SubscriptionPack $subscriptionPack */
        $subscriptionPack = $this->subscriptionPackRepository->findOneBy([
            'carrier' => $carrier,
            'status' => SubscriptionPack::ACTIVE_SUBSCRIPTION_PACK
        ]);

        return $subscriptionPack;
    }

}