<?php

namespace SubscriptionBundle\Service\Action\Subscribe\Event;

use IdentificationBundle\Entity\CarrierInterface;
use Symfony\Component\EventDispatcher\Event;
use SubscriptionBundle\Entity\Subscription;

class AlreadySubscribedEvent extends Event
{
    const NAME = 'subscription.already_subscribed';

    /** @var Subscription */
    protected $subscription;

    /** @var Carrier */
    protected $carrier;

    /**
     * AlreadySubscribedEvent constructor.
     * @param Subscription $subscription
     * @param Carrier $carrier
     */
    public function __construct(Subscription $subscription, Carrier $carrier)
    {
        $this->subscription = $subscription;
        $this->carrier = $carrier;
    }

    /**
     * @return Subscription
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * @return Carrier
     */
    public function getCarrier(): Carrier
    {
        return $this->carrier;
    }
}
