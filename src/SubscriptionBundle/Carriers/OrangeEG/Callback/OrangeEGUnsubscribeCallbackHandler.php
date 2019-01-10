<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 01.11.18
 * Time: 10:26
 */

namespace SubscriptionBundle\Carriers\OrangeEG\Callback;


use AppBundle\Constant\Carrier;
use Symfony\Component\HttpFoundation\Request;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Service\Callback\Impl\CarrierCallbackHandlerInterface;
use SubscriptionBundle\Service\Callback\Impl\HasCommonFlow;
use SubscriptionBundle\Service\Callback\Impl\HasCustomTrackingRules;
use UserBundle\Entity\BillableUser;

class OrangeEGUnsubscribeCallbackHandler implements CarrierCallbackHandlerInterface, HasCommonFlow, HasCustomTrackingRules
{

    public function canHandle(Request $request, int $carrierId): bool
    {
        return $request->get('carrier') === Carrier::ORANGE_EGYPT;
    }

    public function afterProcess(Subscription $subscription, BillableUser $billableUser, ProcessResult $processResponse)
    {
        // TODO: Implement afterSuccess() method.
    }

    public function isNeedToBeTracked(ProcessResult $result): bool
    {
        return true;
    }
}