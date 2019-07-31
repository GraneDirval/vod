<?php

namespace IdentificationBundle\Carriers\HutchID;

use App\Domain\Constants\ConstBillingCarrierId;
use IdentificationBundle\Entity\CarrierInterface;
use IdentificationBundle\Identification\Handler\IdentificationHandlerInterface;
use IdentificationBundle\Identification\Handler\PassthroughFlow\HasPassthroughFlow;
use Symfony\Component\HttpFoundation\Request;


class HutchIDIdentificationHandler implements
    IdentificationHandlerInterface,
    HasPassthroughFlow
{
    /**
     * @param CarrierInterface $carrier
     *
     * @return bool
     */
    public function canHandle(CarrierInterface $carrier): bool
    {
        return false; //$carrier->getBillingCarrierId() === ConstBillingCarrierId::HUTCH_INDONESIA;
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function getAdditionalIdentificationParams(Request $request): array
    {
        return [];
    }
}