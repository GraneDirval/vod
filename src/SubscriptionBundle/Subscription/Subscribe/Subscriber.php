<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 29.10.18
 * Time: 14:58
 */

namespace SubscriptionBundle\Subscription\Subscribe;


use IdentificationBundle\Entity\User;
use Playwing\CrossSubscriptionAPIBundle\Connector\ApiConnector;
use Psr\Log\LoggerInterface;
use SubscriptionBundle\Affiliate\Service\AffiliateVisitSaver;
use SubscriptionBundle\Affiliate\Service\CampaignExtractor;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\BillingFramework\Process\Exception\SubscribingProcessException;
use SubscriptionBundle\CAPTool\Subscription\SubscriptionLimitCompleter;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Entity\SubscriptionPack;
use SubscriptionBundle\Subscription\Common\ProcessResultSuccessChecker;
use SubscriptionBundle\Service\EntitySaveHelper;
use SubscriptionBundle\Subscription\Common\PromotionalResponseChecker;
use SubscriptionBundle\Subscription\Common\SubscriptionFactory;
use SubscriptionBundle\Subscription\Subscribe\Common\SubscribePerformer;
use SubscriptionBundle\Subscription\Subscribe\Common\SubscribePromotionalPerformer;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Subscriber
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var EntitySaveHelper
     */
    private $entitySaveHelper;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var SubscriptionFactory
     */
    private $subscriptionCreator;
    /**
     * @var PromotionalResponseChecker
     */
    private $promotionalResponseChecker;
    /**
     * @var OnSubscribeUpdater
     */
    private $onSubscribeUpdater;
    /**
     * @var SubscriptionLimitCompleter
     */
    private $subscriptionLimitCompleter;
    /**
     * @var SubscribePerformer
     */
    private $subscribePerformer;
    /**
     * @var SubscribePromotionalPerformer
     */
    private $subscribePromotionalPerformer;
    /**
     * @var ApiConnector
     */
    private $crossSubscriptionApi;
    /**
     * @var ProcessResultSuccessChecker
     */
    private $resultSuccessChecker;
    /**
     * @var CampaignExtractor
     */
    private $campaignExtractor;

    /**
     * Subscriber constructor.
     *
     * @param LoggerInterface               $logger
     * @param EntitySaveHelper              $entitySaveHelper
     * @param SessionInterface              $session
     * @param SubscriptionFactory           $subscriptionCreator
     * @param PromotionalResponseChecker    $promotionalResponseChecker
     * @param OnSubscribeUpdater            $onSubscribeUpdater
     * @param SubscriptionLimitCompleter    $subscriptionLimitCompleter
     * @param SubscribePerformer            $subscribePerformer
     * @param SubscribePromotionalPerformer $subscribePromotionalPerformer
     * @param ApiConnector                  $crossSubscriptionApi
     * @param ProcessResultSuccessChecker   $resultSuccessChecker
     * @param CampaignExtractor             $campaignExtractor
     */
    public function __construct(
        LoggerInterface $logger,
        EntitySaveHelper $entitySaveHelper,
        SessionInterface $session,
        SubscriptionFactory $subscriptionCreator,
        PromotionalResponseChecker $promotionalResponseChecker,
        OnSubscribeUpdater $onSubscribeUpdater,
        SubscriptionLimitCompleter $subscriptionLimitCompleter,
        SubscribePerformer $subscribePerformer,
        SubscribePromotionalPerformer $subscribePromotionalPerformer,
        ApiConnector $crossSubscriptionApi,
        ProcessResultSuccessChecker $resultSuccessChecker,
        CampaignExtractor $campaignExtractor
    )
    {
        $this->logger                        = $logger;
        $this->entitySaveHelper              = $entitySaveHelper;
        $this->session                       = $session;
        $this->subscriptionCreator           = $subscriptionCreator;
        $this->promotionalResponseChecker    = $promotionalResponseChecker;
        $this->onSubscribeUpdater            = $onSubscribeUpdater;
        $this->subscriptionLimitCompleter    = $subscriptionLimitCompleter;
        $this->subscribePerformer            = $subscribePerformer;
        $this->subscribePromotionalPerformer = $subscribePromotionalPerformer;
        $this->crossSubscriptionApi          = $crossSubscriptionApi;
        $this->resultSuccessChecker          = $resultSuccessChecker;
        $this->campaignExtractor             = $campaignExtractor;
    }


    /**
     * Subscribe user to given subscription pack
     *
     * @param User             $user
     * @param SubscriptionPack $plan
     * @param array            $additionalData
     *
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function subscribe(User $user, SubscriptionPack $plan, $additionalData = []): array
    {
        $var = AffiliateVisitSaver::extractPageVisitData($this->session, true);

        $this->logger->debug('Creating subscription', ['campaignData' => $var]);

        $subscription = $this->createPendingSubscription($user, $plan);
        $subscription->setAffiliateToken(json_encode($var));

        $campaign                            = $this->campaignExtractor->getCampaignForSubscription($subscription);
        $isFreeTrialSubscriptionFromCampaign = $campaign && $campaign->isFreeTrialSubscription();

        try {

            if ($this->promotionalResponseChecker->isPromotionalResponseNeeded($subscription)) {
                $response = $this->subscribePromotionalPerformer->doSubscribe($subscription);
                if (!$plan->isFirstSubscriptionPeriodIsFree() && !$isFreeTrialSubscriptionFromCampaign) {
                    $response = $this->subscribePerformer->doSubscribe($subscription, $additionalData);
                }
            }
            else {
                $response = $this->subscribePerformer->doSubscribe($subscription, $additionalData);
                if($response->isSuccessful()) {
                    $this->subscribePromotionalPerformer->doSubscribe($subscription);
                }
            }

            $this->onSubscribeUpdater->updateSubscriptionByResponse($subscription, $response);
            $this->subscriptionLimitCompleter->finishProcess($response, $subscription);

            if ($this->resultSuccessChecker->isSuccessful($response)) {
                $this->crossSubscriptionApi->registerSubscription($user->getIdentifier(), $user->getBillingCarrierId());
            }

            return [$subscription, $response];

        } catch (SubscribingProcessException $exception) {
            $subscription->setStatus(Subscription::IS_ERROR);
            $subscription->setError(sprintf('subscribing_process_exception:%s', $exception->getOperationPrefix()));
            throw $exception;
        } finally {
            $this->entitySaveHelper->persistAndSave($subscription);
        }


    }

    /**
     * @param User             $User
     * @param SubscriptionPack $plan
     *
     * @return Subscription
     */
    private function createPendingSubscription(User $User, SubscriptionPack $plan): Subscription
    {
        $subscription = $this->subscriptionCreator->create($User, $plan);
        $subscription->setStatus(Subscription::IS_PENDING);
        $subscription->setCurrentStage(Subscription::ACTION_SUBSCRIBE);
        $this->entitySaveHelper->persistAndSave($subscription);
        return $subscription;
    }

//TODO: remove fake

    /**
     * @param Subscription     $existingSubscription
     * @param SubscriptionPack $plan
     * @param array            $additionalData
     *
     * @return ProcessResult
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws SubscribingProcessException
     */
    public function resubscribe(
        Subscription $existingSubscription,
        SubscriptionPack $plan,
        $additionalData = []
    ): ProcessResult
    {
        $subscription = $existingSubscription;

        try {

            if ($this->promotionalResponseChecker->isPromotionalResponseNeeded($subscription)) {
                $response = $this->subscribePromotionalPerformer->doSubscribe($subscription);
                $this->subscribePerformer->doSubscribe($subscription, $additionalData);
            }
            else {
                $response = $this->subscribePerformer->doSubscribe($subscription, $additionalData);
            }


            $this->onSubscribeUpdater->updateSubscriptionByResponse($subscription, $response);

            $user = $subscription->getUser();

            if ($this->resultSuccessChecker->isSuccessful($response)) {
                $this->crossSubscriptionApi->registerSubscription($user->getIdentifier(), $user->getBillingCarrierId());
            }


            $subscription->setCurrentStage(Subscription::ACTION_SUBSCRIBE);
            return $response;

        } catch (SubscribingProcessException $exception) {
            $subscription->setStatus(Subscription::IS_ERROR);
            throw $exception;
        } finally {
            $this->entitySaveHelper->persistAndSave($subscription);
        }
    }

}