<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 01.08.19
 * Time: 17:49
 */

namespace SubscriptionBundle\Piwik\DataMapper;


use IdentificationBundle\Entity\User;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Piwik\DTO\ConversionEvent;
use SubscriptionBundle\Piwik\Service\AdditionalDataProvider;
use SubscriptionBundle\Piwik\Service\AffiliateStringProvider;

class ConversionEventMapper
{
    /**
     * @var UserInformationMapper
     */
    private $userInformationMapper;
    /**
     * @var AdditionalDataProvider
     */
    private $additionalDataProvider;
    /**
     * @var AffiliateStringProvider
     */
    private $affiliateStringProvider;
    /**
     * @var OrderInformationMapper
     */
    private $informationMapper;


    /**
     * ConversionEventMapper constructor.
     * @param UserInformationMapper                                   $userInformationMapper
     * @param AdditionalDataProvider                                  $additionalDataProvider
     * @param AffiliateStringProvider                                 $affiliateStringProvider
     * @param OrderInformationMapper                                  $informationMapper
     */
    public function __construct(
        UserInformationMapper $userInformationMapper,
        AdditionalDataProvider $additionalDataProvider,
        AffiliateStringProvider $affiliateStringProvider,
        OrderInformationMapper $informationMapper
    )
    {
        $this->userInformationMapper   = $userInformationMapper;
        $this->additionalDataProvider  = $additionalDataProvider;
        $this->affiliateStringProvider = $affiliateStringProvider;
        $this->informationMapper       = $informationMapper;
    }

    public function map(string $type, ProcessResult $processResult, User $user, Subscription $subscription): ConversionEvent
    {
        $provderId = (int)$processResult->getProviderId();

        $userInformation  = $this->userInformationMapper->mapUserInformation(
            $user,
            $subscription,
            $provderId
        );
        $orderInformation = $this->informationMapper->map(
            $processResult->getId(),
            $processResult->getChargePaid(),
            $processResult->isSuccessful(),
            $subscription,
            $type
        );

        return new ConversionEvent($userInformation, $orderInformation);
    }
}