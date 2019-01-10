<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 13.11.18
 * Time: 11:51
 */

namespace SubscriptionBundle\Service\Action\Unsubscribe;


use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessRequestParameters;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Service\Action\Common\RequestParametersProvider;

class UnsubscribeParametersProvider
{
    /**
     * @var RequestParametersProvider
     */
    private $parametersProvider;


    /**
     * UnsubscribeParametersProvider constructor.
     * @param RequestParametersProvider $parametersProvider
     */
    public function __construct(RequestParametersProvider $parametersProvider)
    {
        $this->parametersProvider = $parametersProvider;
    }

    public function provideParameters(Subscription $subscription): ProcessRequestParameters
    {
        return $this->parametersProvider->prepareRequestParameters($subscription);
    }
}