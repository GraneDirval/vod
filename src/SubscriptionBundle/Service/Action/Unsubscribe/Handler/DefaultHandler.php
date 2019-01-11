<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 01.11.18
 * Time: 10:12
 */

namespace SubscriptionBundle\Service\Action\Unsubscribe\Handler;


use AppBundle\Entity\Carrier;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\Entity\Subscription;

class DefaultHandler implements UnsubscriptionHandlerInterface
{

    public function canHandle(Carrier $carrier): bool
    {
        return true;
    }

    public function isPiwikNeedToBeTracked(ProcessResult $processResult): bool
    {
        return true;
    }

    public function applyPostUnsubscribeChanges(Subscription $subscription)
    {
        // TODO: Implement applyPostUnsubscribeChanges() method.
    }
}