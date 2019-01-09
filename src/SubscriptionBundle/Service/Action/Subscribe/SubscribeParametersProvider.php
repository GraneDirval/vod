<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 13.11.18
 * Time: 11:57
 */

namespace SubscriptionBundle\Service\Action\Subscribe;


use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessRequestParameters;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Service\Action\Common\RequestParametersProvider;

class SubscribeParametersProvider
{
    /**
     * @var RequestParametersProvider
     */
    private $parametersProvider;
    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var RouterInterface
     */
    private $router;


    /**
     * SubscribeParametersProvider constructor.
     * @param RequestParametersProvider $parametersProvider
     * @param RequestStack              $requestStack
     * @param RouterInterface           $router
     */
    public function __construct(RequestParametersProvider $parametersProvider, RequestStack $requestStack, RouterInterface $router)
    {
        $this->parametersProvider = $parametersProvider;
        $this->requestStack       = $requestStack;
        $this->router             = $router;
    }

    public function provideParameters(Subscription $subscription, array $additionalInfo): ProcessRequestParameters
    {

        $parameters                 = $this->parametersProvider->prepareRequestParameters($subscription);
        $parameters->additionalData = $additionalInfo;
        $parameters->chargeProduct  = $subscription->getUuid();
        $promotionalTierId          = $subscription->getPromotionTierId();
        $isProviderManaged          = $subscription->getSubscriptionPack()->isProviderManagedSubscriptions();

        $parameters->chargeTier = isset($promotionalTierId) && $isProviderManaged
            ? $promotionalTierId :
            $subscription->getSubscriptionPack()->getTierId();

        $parameters->chargeStrategy = $subscription->getSubscriptionPack()->getBuyStrategyId();
        if ($request = $this->requestStack->getCurrentRequest()) {
            $parameters->redirectUrl = $request->get('location', $this->router->generate('talentica_subscription.wait_listen'));
        }
        return $parameters;
    }

}