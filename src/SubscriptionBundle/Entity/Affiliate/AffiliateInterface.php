<?php

namespace SubscriptionBundle\Entity\Affiliate;

use Doctrine\Common\Collections\Collection;
use IdentificationBundle\Entity\CarrierInterface;

/**
 * Interface AffiliateInterface
 */
interface AffiliateInterface
{
    /**
     * @return string
     */
    public function getUuid(): string;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @return array
     */
    public function getParamsList(): array;

    /**
     * @return array
     */
    public function getInputParamsList(): array;

    /**
     * @return array
     */
    public function getConstantsList(): array;

    /**
     * @return string|null
     */
    public function getPostbackUrl(): ?string;

    /**
     * @return string|null
     */
    public function getSubPriceName(): ?string;

    /**
     * @return Collection
     */
    public function getConstraints(): Collection;

    /**
     * @param string $capType
     * @param CarrierInterface $carrier
     *
     * @return ConstraintByAffiliate|null
     */
    public function getConstraint(string $capType, CarrierInterface $carrier): ?ConstraintByAffiliate;
}