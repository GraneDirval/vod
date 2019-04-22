<?php


namespace SubscriptionBundle\Service\SubscriptionLimiter\Limiter;


use SubscriptionBundle\Service\SubscriptionLimiter\DTO\AffiliateLimiterData;
use SubscriptionBundle\Service\SubscriptionLimiter\DTO\CarrierLimiterData;

class Limiter
{
    /**
     * @var LimiterDataStorage
     */
    private $limiterDataStorage;
    /**
     * @var LimiterDataExtractor
     */
    private $limiterDataExtractor;
    /**
     * @var LimiterDataConverter
     */
    private $limiterDataConverter;

    /**
     * Limiter constructor.
     *
     * @param LimiterDataStorage   $limiterDataStorage
     * @param LimiterDataExtractor $limiterDataExtractor
     * @param LimiterDataConverter $limiterDataConverter
     */
    public function __construct(
        LimiterDataStorage $limiterDataStorage,
        LimiterDataExtractor $limiterDataExtractor,
        LimiterDataConverter $limiterDataConverter
    )
    {
        $this->limiterDataStorage   = $limiterDataStorage;
        $this->limiterDataExtractor = $limiterDataExtractor;
        $this->limiterDataConverter = $limiterDataConverter;
    }

    /**
     * @param CarrierLimiterData|null   $carrierLimiterData
     * @param AffiliateLimiterData|null $affiliateLimiterData
     */
    public function decrProcessingSlots(
        ?CarrierLimiterData $carrierLimiterData,
        ?AffiliateLimiterData $affiliateLimiterData
    ): void
    {
        if ($carrierLimiterData) {
            $slots           = $this->limiterDataExtractor->getCarrierSlots($carrierLimiterData);
            $processingSlots = $slots[LimiterDataConverter::PROCESSING_SLOTS]--;

            if (!isset($slots[LimiterDataConverter::SLOTS])) {
                $slots[LimiterDataConverter::SLOTS] = $carrierLimiterData->getCarrier()->getNumberOfAllowedSubscriptionsByConstraint();
            };

            if ($processingSlots >= 0) {
                $this->limiterDataStorage->updateCarrierConstraints(
                    $carrierLimiterData->getCarrier()->getBillingCarrierId(),
                    $slots
                );
            }
        }

        if ($affiliateLimiterData) {
            $slots           = $this->limiterDataExtractor->getAffiliateSlots($affiliateLimiterData);
            $processingSlots = $slots[LimiterDataConverter::PROCESSING_SLOTS]--;
            if ($processingSlots >= 0) {
                $this->limiterDataStorage->updateAffiliateConstraints(
                    $affiliateLimiterData->getBillingCarrierId(),
                    $affiliateLimiterData->getAffiliate()->getUuid(),
                    $affiliateLimiterData->getConstraintByAffiliate()->getUuid(),
                    $slots
                );
            }
        }
    }

    /**
     * @param CarrierLimiterData|null   $carrierLimiterData
     * @param AffiliateLimiterData|null $affiliateLimiterData
     */
    public function incrProcessingSlots(
        ?CarrierLimiterData $carrierLimiterData,
        ?AffiliateLimiterData $affiliateLimiterData
    ): void
    {
        if ($carrierLimiterData) {
            $slots = $this->limiterDataExtractor->getCarrierSlots($carrierLimiterData);
            $slots[LimiterDataConverter::PROCESSING_SLOTS]++;

            if (!isset($slots[LimiterDataConverter::SLOTS])) {
                $slots[LimiterDataConverter::SLOTS] = $carrierLimiterData->getCarrier()->getNumberOfAllowedSubscriptionsByConstraint();
            }

            $this->limiterDataStorage->updateCarrierConstraints(
                $carrierLimiterData->getCarrier()->getBillingCarrierId(),
                $slots
            );
        }

        if ($affiliateLimiterData) {
            $slots = $this->limiterDataExtractor->getAffiliateSlots($affiliateLimiterData);
            $slots[LimiterDataConverter::PROCESSING_SLOTS]++;
            $this->limiterDataStorage->updateAffiliateConstraints(
                $affiliateLimiterData->getBillingCarrierId(),
                $affiliateLimiterData->getAffiliate()->getUuid(),
                $affiliateLimiterData->getConstraintByAffiliate()->getUuid(),
                $slots
            );
        }
    }

    /**
     * @param CarrierLimiterData|null   $carrierLimiterData
     * @param AffiliateLimiterData|null $affiliateLimiterData
     */
    public function decrSubscriptionSlots(
        ?CarrierLimiterData $carrierLimiterData,
        ?AffiliateLimiterData $affiliateLimiterData
    ): void
    {
        if ($carrierLimiterData) {
            $slots     = $this->limiterDataExtractor->getCarrierSlots($carrierLimiterData);
            $openSlots = $slots[LimiterDataConverter::OPEN_SUBSCRIPTION_SLOTS]--;

            if (!isset($slots[LimiterDataConverter::SLOTS])) {
                $slots[LimiterDataConverter::SLOTS] = $carrierLimiterData->getCarrier()->getNumberOfAllowedSubscriptionsByConstraint();
            }

            if ($openSlots >= 0) {
                $this->limiterDataStorage->updateCarrierConstraints(
                    $carrierLimiterData->getCarrier()->getBillingCarrierId(),
                    $slots
                );
            }
        }

        if ($affiliateLimiterData) {
            $slots     = $this->limiterDataExtractor->getAffiliateSlots($affiliateLimiterData);
            $openSlots = $slots[LimiterDataConverter::OPEN_SUBSCRIPTION_SLOTS]--;
            if ($openSlots >= 0) {
                $this->limiterDataStorage->updateAffiliateConstraints(
                    $affiliateLimiterData->getBillingCarrierId(),
                    $affiliateLimiterData->getAffiliate()->getUuid(),
                    $affiliateLimiterData->getConstraintByAffiliate()->getUuid(),
                    $slots
                );
            }
        }
    }
}