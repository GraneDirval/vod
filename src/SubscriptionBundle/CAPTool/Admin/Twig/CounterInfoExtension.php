<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 10.07.19
 * Time: 12:20
 */

namespace SubscriptionBundle\CAPTool\Admin\Twig;


use SubscriptionBundle\CAPTool\Admin\Service\ConstraintByAffiliateCapCalculator;
use SubscriptionBundle\CAPTool\Subscription\Limiter\LimiterStorage;
use SubscriptionBundle\Entity\Affiliate\ConstraintByAffiliate;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CounterInfoExtension extends AbstractExtension
{
    /**
     * @var ConstraintByAffiliateCapCalculator
     */
    private $affiliateCapCalculator;


    /**
     * CounterInfoExtension constructor.
     * @param LimiterStorage $limiterStorage
     */
    public function __construct(ConstraintByAffiliateCapCalculator $affiliateCapCalculator)
    {
        $this->affiliateCapCalculator = $affiliateCapCalculator;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getCounter', function (ConstraintByAffiliate $constraintByAffiliate) {
                return $this->affiliateCapCalculator->calculateCounter($constraintByAffiliate);
            })
        ];
    }

}