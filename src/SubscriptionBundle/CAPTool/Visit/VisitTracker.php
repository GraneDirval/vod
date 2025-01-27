<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 13.05.19
 * Time: 16:10
 */

namespace SubscriptionBundle\CAPTool\Visit;

use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use Psr\Log\LoggerInterface;
use SubscriptionBundle\Entity\Affiliate\CampaignInterface;

class VisitTracker
{
    /**
     * @var ConstraintValidityChecker
     */
    private $constraintValidityChecker;
    /**
     * @var VisitStorage
     */
    private $visitStorage;
    /**
     * @var KeyGenerator
     */
    private $keyGenerator;
    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * VisitTracker constructor.
     *
     * @param ConstraintValidityChecker $constraintValidityChecker
     * @param VisitStorage              $visitStorage
     * @param KeyGenerator              $keyGenerator
     * @param LoggerInterface           $logger
     */
    public function __construct(
        ConstraintValidityChecker $constraintValidityChecker,
        VisitStorage $visitStorage,
        KeyGenerator $keyGenerator,
        LoggerInterface $logger
    )
    {
        $this->constraintValidityChecker = $constraintValidityChecker;
        $this->visitStorage              = $visitStorage;
        $this->keyGenerator              = $keyGenerator;
        $this->logger                    = $logger;
    }

    public function trackVisit(CarrierInterface $carrier, CampaignInterface $campaign, string $visitInfo)
    {
        $affiliate             = $campaign->getAffiliate();
        $constraintByAffiliate = null;

        $key = $this->keyGenerator->generateVisitKey($carrier, $affiliate);
        $this->visitStorage->storeVisit($key, $visitInfo);
        $this->logger->debug('CAP Track visit', ['key' => $key]);
    }

}