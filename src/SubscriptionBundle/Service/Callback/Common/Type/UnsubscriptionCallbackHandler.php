<?php
/**
 * Created by IntelliJ IDEA.
 * User: bharatm
 * Date: 28/08/17
 * Time: 6:35 PM
 */

namespace SubscriptionBundle\Service\Callback\Common\Type;


use PiwikBundle\Service\NewTracker;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\BillingFramework\Process\UnsubscribeProcess;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Service\Action\Unsubscribe\OnUnsubscribeUpdater;
use SubscriptionBundle\Service\Callback\Common\SubscriptionStatusChanger;

class UnsubscriptionCallbackHandler extends AbstractCallbackHandler
{
    /**
     * @var \SubscriptionBundle\BillingFramework\\SubscriptionBundle\Service\Action\Common\OnUnsubscribeUpdater
     */
    private $onUnsubscribeUpdater;


    /**
     * UnsubscriptionCallbackHandler constructor.
     * @param \SubscriptionBundle\BillingFramework\\SubscriptionBundle\Service\Action\Common\OnUnsubscribeUpdater $onUnsubscribeUpdater
     */
    public function __construct(
        OnUnsubscribeUpdater $onUnsubscribeUpdater
    )
    {
        $this->onUnsubscribeUpdater = $onUnsubscribeUpdater;
    }


    public function isSupport($type): bool
    {
        return $type == UnsubscribeProcess::PROCESS_METHOD_UNSUBSCRIBE;
    }


    public function getPiwikEventName(): string
    {
        return NewTracker::TRACK_UNSUBSCRIBE;
    }


    public function updateSubscriptionByCallbackData(Subscription $subscription, ProcessResult $response)
    {
        $subscription->setCurrentStage(Subscription::ACTION_UNSUBSCRIBE);


        $this->onUnsubscribeUpdater->updateSubscriptionByCallbackResponse($subscription, $response);
    }

}