<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 30.04.18
 * Time: 12:15
 */

namespace SubscriptionBundle\Carriers\TelenorPK\Subscribe;


use IdentificationBundle\BillingFramework\ID;
use IdentificationBundle\Entity\User;
use IdentificationBundle\Identification\Service\RouteProvider;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Subscription\Notification\Notifier;
use SubscriptionBundle\Subscription\Subscribe\Handler\HasCommonFlow;
use SubscriptionBundle\Subscription\Subscribe\Handler\HasCustomResponses;
use SubscriptionBundle\Subscription\Subscribe\Handler\SubscriptionHandlerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TelenorPKSubscribeHandler implements SubscriptionHandlerInterface, HasCommonFlow, HasCustomResponses
{
    /**
     * @var Notifier
     */
    private $notifier;
    /**
     * @var RouteProvider
     */
    private $routeProvider;


    /**
     * TelenorPKSubscribeHandler constructor.
     * @param Notifier      $notifier
     * @param RouteProvider $routeProvider
     */
    public function __construct(Notifier $notifier, RouteProvider $routeProvider)
    {
        $this->notifier      = $notifier;
        $this->routeProvider = $routeProvider;
    }

    public function canHandle(\CommonDataBundle\Entity\Interfaces\CarrierInterface $carrier): bool
    {
        return $carrier->getBillingCarrierId() === ID::TELENOR_PAKISTAN_DOT;
    }

    public function getAdditionalSubscribeParams(Request $request, User $User): array
    {
        return [];
    }


    public function afterProcess(Subscription $subscription, \SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult $result)
    {
        // TODO: Implement afterProcess() method.
    }


    /**
     * @param Request      $request
     * @param User         $User
     * @param Subscription $subscription
     * @return Response|null
     */
    public function createResponseForSuccessfulSubscribe(Request $request, User $User, Subscription $subscription)
    {
        if ($subscription->getError() === 'already_subscribed_on_another_service') {
            return new RedirectResponse($this->routeProvider->getLinkToHomepage(['err_handle' => 'already_subscribed_on_another_service']));
        }
    }

    /**
     * @param Request      $request
     * @param User         $User
     * @param Subscription $subscription
     * @return Response|null
     */
    public function createResponseForExistingSubscription(Request $request, User $User, Subscription $subscription)
    {
        // TODO: Implement createResponseForExistingSubscription() method.
    }
}