<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 06.08.19
 * Time: 11:51
 */

namespace SubscriptionBundle\Piwik\DataMapper;


use SubscriptionBundle\Affiliate\Service\CampaignExtractor;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Piwik\DTO\OrderInformation;
use SubscriptionBundle\Piwik\Service\CurrencyConverter;
use SubscriptionBundle\Piwik\Service\OrderNameProvider;

class OrderInformationMapper
{
    /**
     * @var CampaignExtractor
     */
    private $campaignExtractor;
    /**
     * @var OrderNameProvider
     */
    private $orderNameProvider;
    /**
     * @var CurrencyConverter
     */
    private $converter;


    /**
     * OrderInformationMapper constructor.
     * @param CampaignExtractor $campaignExtractor
     * @param OrderNameProvider $orderNameProvider
     * @param CurrencyConverter $converter
     */
    public function __construct(
        CampaignExtractor $campaignExtractor,
        OrderNameProvider $orderNameProvider,
        CurrencyConverter $converter
    )
    {
        $this->campaignExtractor = $campaignExtractor;
        $this->orderNameProvider = $orderNameProvider;
        $this->converter         = $converter;
    }

    /**
     * @param Subscription $subscription
     * @param string       $action
     * @param bool         $resultStatus
     * @param int|null     $responseId
     * @param int|null     $chargePaid
     * @return OrderInformation
     */
    public function map(
        Subscription $subscription,
        string $action,
        bool $resultStatus,
        ?int $responseId,
        ?int $chargePaid
    ): OrderInformation
    {

        $subscriptionPack = $subscription->getSubscriptionPack();
        $campaign         = $this->campaignExtractor->getCampaignForSubscription($subscription);

        $subscriptionPackId = abs($subscriptionPack->getUuid());
        $eurPrice           = $this->converter->convert($subscriptionPack->getTierCurrency(), $subscriptionPack->getTierPrice());
        $subscriptionPrice  = round($subscriptionPack->getTierPrice(), 2);
        $name               = $this->orderNameProvider->get(
            $action,
            $resultStatus,
            $chargePaid,
            $subscriptionPack,
            $campaign
        );
        $orderIdPieces      = [
            $name,
            $subscription->getUuid(),
            $subscriptionPackId,
            $responseId ?: 'N' . rand(1000, 9999),
            $subscriptionPrice,
            mt_rand(0, 9999)
        ];
        $orderId            = implode('-', $orderIdPieces);

        return new OrderInformation(
            $orderId,
            $eurPrice,
            $name . '-' . $subscriptionPackId,
            $action,
            $subscriptionPack->getTierCurrency()
        );
    }

}