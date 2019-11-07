<?php


namespace Carriers\OrangeEGTpay\OneClick;


use App\Domain\Service\OneClickFlow\OneClickFlowInterface;
use App\Domain\Service\OneClickFlow\OneClickFlowParameters;
use IdentificationBundle\BillingFramework\ID;

class OrangeEGTpayOneClickFlow implements OneClickFlowInterface
{
    /**
     * @param int $billingCarrierId
     * @param int $flowType
     *
     * @return bool
     */
    public function canHandle(int $billingCarrierId, int $flowType): bool
    {
        return $billingCarrierId === ID::ORANGE_EGYPT_TPAY && $flowType === $this->getFlowType();
    }

    /**
     * @return int|null
     */
    public function getFlowType(): int
    {
        return OneClickFlowParameters::LP_OFF;
    }
}