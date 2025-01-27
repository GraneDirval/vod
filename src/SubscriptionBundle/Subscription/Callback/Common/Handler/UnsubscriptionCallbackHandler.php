<?php
/**
 * Created by IntelliJ IDEA.
 * User: bharatm
 * Date: 28/08/17
 * Time: 6:35 PM
 */

namespace SubscriptionBundle\Subscription\Callback\Common\Handler;


use PiwikBundle\Service\EventPublisher;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\BillingFramework\Process\UnsubscribeProcess;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Subscription\Callback\Common\SubscriptionStatusChanger;
use SubscriptionBundle\Subscription\Unsubscribe\OnUnsubscribeUpdater;

class UnsubscriptionCallbackHandler implements CallbackHandlerInterface
{
    private $onUnsubscribeUpdater;


    /**
     * UnsubscriptionCallbackHandler constructor.
     * @param OnUnsubscribeUpdater $onUnsubscribeUpdater
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
        return 'unsubscribe';
    }


    public function doProcess(Subscription $subscription, ProcessResult $response): void
    {
        $subscription->setCurrentStage(Subscription::ACTION_UNSUBSCRIBE);


        $this->onUnsubscribeUpdater->updateSubscriptionByCallbackResponse($subscription, $response);
    }

    public function afterProcess(Subscription $subscription, ProcessResult $response): void
    {
        // TODO: Implement afterProcess() method.
    }


    public function isActionAllowed(Subscription $subscription): bool
    {
        if ($subscription->isActive()) {
            return true;
        }

        return false;
    }
}