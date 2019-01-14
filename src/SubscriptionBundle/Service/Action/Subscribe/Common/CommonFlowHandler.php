<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 26.04.18
 * Time: 14:06
 */

namespace SubscriptionBundle\Service\Action\Subscribe\Common;

use SubscriptionBundle\Affiliate\Service\AffiliateSender;
use SubscriptionBundle\Affiliate\Service\UserInfoMapper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\Controller\Traits\ResponseTrait;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Exception\ActiveSubscriptionPackNotFound;
use SubscriptionBundle\Exception\ExistingSubscriptionException;
use SubscriptionBundle\Piwik\PiwikStatisticSender;
use SubscriptionBundle\Service\Action\Common\RedirectUrlNullifier;
use SubscriptionBundle\Service\Action\Subscribe\Handler\HasCommonFlow;
use SubscriptionBundle\Service\Action\Subscribe\Handler\HasCustomResponses;
use SubscriptionBundle\Service\Action\Subscribe\Handler\HasCustomTrackingRules;
use SubscriptionBundle\Service\Action\Subscribe\Handler\SubscriptionHandlerProvider;
use SubscriptionBundle\Service\Action\Subscribe\Subscriber;
use SubscriptionBundle\Service\EntitySaveHelper;
use SubscriptionBundle\Service\SubscriptionPackProvider;
use SubscriptionBundle\Service\SubscriptionExtractor;
use SubscriptionBundle\Utils\UrlParamAppender;
use IdentificationBundle\Entity\User;

class CommonFlowHandler
{

    use ResponseTrait;
    /**
     * @var SubscriptionPackProvider
     */
    private $subscriptionPackProvider;
    /**
     * @var Subscriber
     */
    private $subscriber;
    /**
     * @var SubscriptionEligibilityChecker
     */
    private $checker;
    /**
     * @var SubscriptionExtractor
     */
    private $subscriptionProvider;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var RedirectUrlNullifier
     */
    private $redirectUrlNullifier;
    /**
     * @var SubscriptionHandlerProvider
     */
    private $handlerProvider;
    /**
     * @var CommonResponseCreator
     */
    private $commonResponseCreator;
    /**
     * @var UrlParamAppender
     */
    private $urlParamAppender;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var AffiliateSender
     */
    private $affiliateService;
    /**
     * @var PiwikStatisticSender
     */
    private $piwikStatisticSender;
    /**
     * @var UserInfoMapper
     */
    private $infoMapper;
    /**
     * @var EntityManagerHelper
     */
    private $entitySaveHelper;


    /**
     * CommonSubscriber constructor.
     *
     * @param SubscriptionExtractor          $subscriptionProvider
     * @param SubscriptionPackProvider       $subscriptionPackProvider
     * @param Subscriber                     $subscriber
     * @param SubscriptionEligibilityChecker $checker
     * @param LoggerInterface                $logger
     * @param RedirectUrlNullifier           $redirectUrlNullifier
     * @param SubscriptionHandlerProvider    $handlerProvider
     * @param CommonResponseCreator          $commonResponseCreator
     * @param UrlParamAppender               $urlParamAppender
     * @param RouterInterface                $router
     * @param AffiliateSender               $affiliateService
     * @param PiwikStatisticSender           $piwikStatisticSender
     * @param UserInfoMapper                 $infoMapper
     * @param EntitySaveHelper               $entitySaveHelper
     */
    public function __construct(
        SubscriptionExtractor $subscriptionProvider,
        SubscriptionPackProvider $subscriptionPackProvider,
        Subscriber $subscriber,
        SubscriptionEligibilityChecker $checker,
        LoggerInterface $logger,
        RedirectUrlNullifier $redirectUrlNullifier,
        SubscriptionHandlerProvider $handlerProvider,
        CommonResponseCreator $commonResponseCreator,
        UrlParamAppender $urlParamAppender,
        RouterInterface $router,
        AffiliateSender $affiliateService,
        PiwikStatisticSender $piwikStatisticSender,
        UserInfoMapper $infoMapper,
        EntitySaveHelper $entitySaveHelper
    )
    {
        $this->subscriptionPackProvider = $subscriptionPackProvider;
        $this->subscriber               = $subscriber;
        $this->checker                  = $checker;
        $this->subscriptionProvider     = $subscriptionProvider;
        $this->logger                   = $logger;
        $this->redirectUrlNullifier     = $redirectUrlNullifier;
        $this->handlerProvider          = $handlerProvider;
        $this->commonResponseCreator    = $commonResponseCreator;
        $this->urlParamAppender         = $urlParamAppender;
        $this->router                   = $router;
        $this->affiliateService         = $affiliateService;
        $this->piwikStatisticSender     = $piwikStatisticSender;
        $this->infoMapper               = $infoMapper;
        $this->entitySaveHelper         = $entitySaveHelper;
    }


    /**
     * @param Request      $request
     * @param User $User
     * @return Response
     * @throws ActiveSubscriptionPackNotFound
     * @throws ExistingSubscriptionException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function process(Request $request, User $User): Response
    {
        $UserId         = $User->getUuid();
        $UserIdentifier = $User->getIdentifier();
        $this->logger->debug('Processing `subscribe` action', [
            'UserId' => $UserId,
            'msidsn'         => $UserIdentifier
        ]);

        /** @var HasCommonFlow $subscriber */
        $subscriber   = $this->handlerProvider->getSubscriber($User->getCarrier());
        $subscription = $this->subscriptionProvider->getExistingSubscriptionForUser($User);

        if (!empty($subscription) && !$this->checker->isEligibleToSubscribe($subscription)) {
            $this->logger->debug('`Subscribe` is not possible. User already have an active subscription.');
            if (
                $subscriber instanceof HasCustomResponses &&
                $response = $subscriber->createResponseForExistingSubscription($request, $User, $subscription)) {
                return $response;
            }

            $redirect     = $request->get('redirect', false);
            $redirect_url = $request->get('location', '/');
            $updatedUrl   = $this->urlParamAppender->appendUrl($redirect_url, [
                'err_handle' => 'already_subscribed'
            ]);

            if ($redirect) {
                return new RedirectResponse($updatedUrl);
            }

            throw new ExistingSubscriptionException('You already have an active subscription.', $subscription);
        }


        if (empty($subscription)) {
            return $this->handleSubscribe($request, $User, $subscriber);
        } else {
            return $this->handleResubscribe($request, $User, $subscription, $subscriber);
        }


    }


    /**
     * @param Request      $request
     * @param User $User
     * @param Subscription $subscription
     * @param              $subscriber
     * @return \Symfony\Component\HttpFoundation\JsonResponse|RedirectResponse|Response
     * @throws ActiveSubscriptionPackNotFound
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function handleResubscribe(
        Request $request,
        User $User,
        Subscription $subscription,
        HasCommonFlow $subscriber
    ): Response
    {

        $subscriptionPack = $this->subscriptionPackProvider->getActiveSubscriptionPack($User);
        $subpackId        = $subscriptionPack->getUuid();
        $subpackName      = $subscriptionPack->getName();


        // We have same property at Carrier.
        // Maybe we need to remove this duplicate?
        if ($subscriptionPack->isResubAllowed() || $subscription->isOutOfCredits()) {
            $this->logger->debug('Resubscription is allowed. Doing resubscribe', [
                'packId'      => $subpackId,
                'carrierName' => $subpackName
            ]);

            $additionalData = $subscriber->getAdditionalSubscribeParams($request, $User);
            $result         = $this->subscriber->resubscribe($subscription, $subscriptionPack, $additionalData);

        } else {
            $this->logger->debug('Resubscription is not allowed.', [
                'packId'      => $subpackId,
                'carrierName' => $subpackName
            ]);

            if ($request->get('is_ajax_request', null)) {
                return $this->getSimpleJsonResponse('', 200, [], ['resub_not_allowed' => true]);
            } else {
                return new RedirectResponse($this->router->generate('resub_not_allowed'));
            }
        }


        if ($subscriber instanceof HasCustomTrackingRules) {
            $isNeedToBeTracked = $subscriber->isNeedToBeTrackedForResubscribe($result);
        } else {
            $isNeedToBeTracked = ($result->isSuccessful() && $result->isFinal());;
        }

        if ($isNeedToBeTracked) {
            $this->subscriber->trackEventsForResubscribe($subscription, $result);
        }

        $subscriber->afterProcess($subscription, $result);
        $this->entitySaveHelper->saveAll();

        return $this->commonResponseCreator->createCommonHttpResponse($request, $User);


    }

    /**
     * @param Request       $request
     * @param User  $User
     * @param HasCommonFlow $subscriber
     * @return null|Response
     * @throws ActiveSubscriptionPackNotFound
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function handleSubscribe(Request $request, User $User, HasCommonFlow $subscriber): Response
    {

        $additionalData   = $subscriber->getAdditionalSubscribeParams($request, $User);
        $subscriptionPack = $this->subscriptionPackProvider->getActiveSubscriptionPack($User);
        /** @var ProcessResult $result */
        list($newSubscription, $result) = $this->subscriber->subscribe($User, $subscriptionPack, $additionalData);

        if ($subscriber instanceof HasCustomTrackingRules) {
            $isNeedToBeTracked = $subscriber->isNeedToBeTrackedForSubscribe($result);
        } else {
            $isNeedToBeTracked = ($result->isSuccessful() && $result->isFinal());
        }

        if ($isNeedToBeTracked) {
            $this->subscriber->trackEventsForSubscribe($newSubscription, $result);
        }
        $subscriber->afterProcess($newSubscription, $result);
        $this->entitySaveHelper->saveAll();

        if ($subscriber instanceof HasCustomResponses &&
            $customResponse = $subscriber->createResponseForSuccessfulSubscribe($request, $User, $newSubscription)) {
            return $customResponse;
        }

        return $this->commonResponseCreator->createCommonHttpResponse($request, $User);

    }

}