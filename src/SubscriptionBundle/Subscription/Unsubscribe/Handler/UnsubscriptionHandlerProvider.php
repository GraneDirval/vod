<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 01.11.18
 * Time: 10:06
 */

namespace SubscriptionBundle\Subscription\Unsubscribe\Handler;


use CommonDataBundle\Entity\Interfaces\CarrierInterface;

class UnsubscriptionHandlerProvider
{


    /**
     * @var UnsubscriptionHandlerInterface[]
     */
    private $unsubscribers = [];

    private $defaultHandler;

    /**
     * UnsubscriptionHandlerProvider constructor.
     * @param $defaultHandler
     */
    public function __construct(DefaultHandler $defaultHandler)
    {
        $this->defaultHandler = $defaultHandler;
    }

    /**
     * @param UnsubscriptionHandlerInterface $handler
     */
    public function addHandler(UnsubscriptionHandlerInterface $handler)
    {
        $this->unsubscribers[] = $handler;
    }

    /**
     * @param CarrierInterface $carrier
     * @return UnsubscriptionHandlerInterface
     */
    public function getUnsubscriptionHandler(CarrierInterface $carrier): UnsubscriptionHandlerInterface
    {
        /** @var UnsubscriptionHandlerInterface $subscriber */
        foreach ($this->unsubscribers as $subscriber) {
            if ($subscriber->canHandle($carrier)) {
                return $subscriber;
            }
        }

        return $this->defaultHandler;
    }

}