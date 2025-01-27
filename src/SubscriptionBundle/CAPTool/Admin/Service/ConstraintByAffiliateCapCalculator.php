<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 10.07.19
 * Time: 12:26
 */

namespace SubscriptionBundle\CAPTool\Admin\Service;


use SubscriptionBundle\CAPTool\Subscription\Limiter\LimiterStorage;
use SubscriptionBundle\CAPTool\Subscription\Limiter\StorageKeyGenerator;
use SubscriptionBundle\CAPTool\Visit\KeyGenerator;
use SubscriptionBundle\CAPTool\Visit\VisitStorage;
use SubscriptionBundle\Entity\Affiliate\ConstraintByAffiliate;

class ConstraintByAffiliateCapCalculator
{
    /**
     * @var StorageKeyGenerator
     */
    private $storageKeyGenerator;
    /**
     * @var LimiterStorage
     */
    private $limiterStorage;
    /**
     * @var KeyGenerator
     */
    private $keyGenerator;
    /**
     * @var VisitStorage
     */
    private $visitStorage;


    /**
     * ConstraintByAffiliateCapCalculator constructor.
     * @param StorageKeyGenerator $storageKeyGenerator
     * @param LimiterStorage      $limiterStorage
     * @param KeyGenerator        $keyGenerator
     * @param VisitStorage        $visitStorage
     */
    public function __construct(
        StorageKeyGenerator $storageKeyGenerator,
        LimiterStorage $limiterStorage,
        KeyGenerator $keyGenerator,
        VisitStorage $visitStorage
    )
    {
        $this->storageKeyGenerator = $storageKeyGenerator;
        $this->limiterStorage      = $limiterStorage;
        $this->keyGenerator        = $keyGenerator;
        $this->visitStorage        = $visitStorage;
    }

    public function calculateCounter(ConstraintByAffiliate $subject): int
    {

        if ($subject->getCapType() === ConstraintByAffiliate::CAP_TYPE_SUBSCRIBE) {
            $key       = $this->storageKeyGenerator->generateAffiliateConstraintKey($subject);
            $pending   = $this->limiterStorage->getPendingSubscriptionAmount($key);
            $finished  = $this->limiterStorage->getFinishedSubscriptionAmount($key);
            $available = $pending + $finished;
        } else {
            $key       = $this->keyGenerator->generateVisitKey(
                $subject->getCarrier(),
                $subject->getAffiliate()
            );
            $available = $this->visitStorage->getVisitCount($key, new \DateTimeImmutable());
        }

        return (int)$available;

    }
}