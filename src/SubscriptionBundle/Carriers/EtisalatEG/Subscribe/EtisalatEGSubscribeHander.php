<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 26.04.18
 * Time: 15:20
 */

namespace SubscriptionBundle\Carriers\EtisalatEG\Subscribe;


use IdentificationBundle\BillingFramework\ID;
use IdentificationBundle\BillingFramework\Process\DTO\PinRequestResult;
use IdentificationBundle\Entity\User;
use IdentificationBundle\WifiIdentification\Service\WifiIdentificationDataStorage;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Subscription\Subscribe\Handler\HasCommonFlow;
use SubscriptionBundle\Subscription\Subscribe\Handler\SubscriptionHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class EtisalatEGSubscribeHander implements SubscriptionHandlerInterface, HasCommonFlow
{
    /**
     * @var WifiIdentificationDataStorage
     */
    private $wifiIdentificationDataStorage;


    /**
     * EtisalatEGSubscribeHander constructor.
     * @param WifiIdentificationDataStorage $identificationDataStorage
     */
    public function __construct(WifiIdentificationDataStorage $identificationDataStorage)
    {
        $this->wifiIdentificationDataStorage = $identificationDataStorage;
    }

    public function canHandle(\CommonDataBundle\Entity\Interfaces\CarrierInterface $carrier): bool
    {
        return in_array($carrier->getBillingCarrierId(), [
            ID::ETISALAT_EGYPT,
        ]);
    }

    public function getAdditionalSubscribeParams(Request $request, User $User): array
    {
        /** @var PinRequestResult $pinRequestResult */
        $pinRequestResult = $this->wifiIdentificationDataStorage->getPinRequestResult();

        $contractId = $pinRequestResult->getRawData()['subscription_contract_id'];

        return [
            'subscription_contract_id' => $contractId,
            'url_id'                   => $User->getShortUrlId()
        ];
    }

    public function afterProcess(Subscription $subscription, \SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult $result)
    {
        // TODO: Implement performPostSubscribeActions() method.
    }

}