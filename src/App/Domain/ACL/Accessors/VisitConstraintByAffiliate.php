<?php

namespace App\Domain\ACL\Accessors;

use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use SubscriptionBundle\Entity\Affiliate\ConstraintByAffiliate;
use SubscriptionBundle\CAPTool\Visit\VisitChecker;

/**
 * Class VisitConstraintByAffiliateService
 */
class VisitConstraintByAffiliate
{
    /**
     * @var VisitChecker
     */
    private $checker;

    /**
     * AbstractConstraintByAffiliateService constructor
     *
     * @param VisitChecker $checker
     */
    public function __construct(VisitChecker $checker)
    {
        $this->checker = $checker;
    }

    /**
     * @param CarrierInterface      $carrier
     * @param ConstraintByAffiliate $constraint
     * @return bool
     */
    public function canVisit(CarrierInterface $carrier, ConstraintByAffiliate $constraint): bool
    {
        return !$this->checker->isCapReached($carrier, $constraint);
    }
}