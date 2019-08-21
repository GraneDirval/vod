<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 29.10.18
 * Time: 15:08
 */

namespace SubscriptionBundle\Service\Action\Unsubscribe;


use Playwing\CrossSubscriptionAPIBundle\Connector\ApiConnector;
use Psr\Log\LoggerInterface;
use SubscriptionBundle\BillingFramework\Notification\API\Exception\NotificationSendFailedException;
use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessResult;
use SubscriptionBundle\BillingFramework\Process\Exception\UnsubscribingProcessException;
use SubscriptionBundle\BillingFramework\Process\UnsubscribeProcess;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Entity\SubscriptionPack;
use SubscriptionBundle\Piwik\SubscriptionStatisticSender;
use SubscriptionBundle\Service\Action\Common\FakeResponseProvider;
use SubscriptionBundle\Service\EntitySaveHelper;
use SubscriptionBundle\Service\Notification\Notifier;

class Unsubscriber
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
     * @var FakeResponseProvider
     */
    private $fakeResponseProvider;
    /**
     * @var \SubscriptionBundle\Service\Notification\Notifier
     */
    private $notifier;
    /**
     * @var UnsubscribeProcess
     */
    private $unsubscribeProcess;
    /**
     * @var OnUnsubscribeUpdater
     */
    private $onUnsubscribeUpdater;

    /**
     * @var SubscriptionStatisticSender
     */
    private $subscriptionStatisticSender;
    /**
     * @var UnsubscribeParametersProvider
     */
    private $parametersProvider;
    /**
     * @var ApiConnector
     */
    private $crossSubscriptionApi;


    /**
     * Unsubscriber constructor.
     * @param LoggerInterface                                             $logger
     * @param EntitySaveHelper                                            $entitySaveHelper
     * @param FakeResponseProvider                                        $fakeResponseProvider
     * @param Notifier                                                    $notifier
     * @param UnsubscribeProcess                                          $unsubscribeProcess
     * @param OnUnsubscribeUpdater                                        $onUnsubscribeUpdater
     * @param SubscriptionStatisticSender                                 $subscriptionStatisticSender
     * @param UnsubscribeParametersProvider                               $parametersProvider
     * @param \Playwing\CrossSubscriptionAPIBundle\Connector\ApiConnector $crossSubscriptionApi
     */
    public function __construct(
        LoggerInterface $logger,
        EntitySaveHelper $entitySaveHelper,
        FakeResponseProvider $fakeResponseProvider,
        Notifier $notifier,
        UnsubscribeProcess $unsubscribeProcess,
        OnUnsubscribeUpdater $onUnsubscribeUpdater,
        SubscriptionStatisticSender $subscriptionStatisticSender,
        UnsubscribeParametersProvider $parametersProvider,
        ApiConnector $crossSubscriptionApi
    )
    {
        $this->logger                      = $logger;
        $this->entitySaveHelper            = $entitySaveHelper;
        $this->fakeResponseProvider        = $fakeResponseProvider;
        $this->notifier                    = $notifier;
        $this->unsubscribeProcess          = $unsubscribeProcess;
        $this->onUnsubscribeUpdater        = $onUnsubscribeUpdater;
        $this->subscriptionStatisticSender = $subscriptionStatisticSender;
        $this->parametersProvider          = $parametersProvider;
        $this->crossSubscriptionApi        = $crossSubscriptionApi;
    }

    public function unsubscribe(
        Subscription $subscription,
        SubscriptionPack $subscriptionPack,
        array $additionalParameters = []
    )
    {
        $subscription->setStatus(Subscription::IS_PENDING);
        $subscription->setCurrentStage(Subscription::ACTION_UNSUBSCRIBE);
        $this->entitySaveHelper->persistAndSave($subscription);

        if (!$subscriptionPack->isProviderManagedSubscriptions()) {

            $previousStatus = $subscription->getStatus();
            $previousStage  = $subscription->getCurrentStage();
            $response       = $this->fakeResponseProvider->getDummyResult(
                $subscription,
                UnsubscribeProcess::PROCESS_METHOD_UNSUBSCRIBE
            );

            try {
                $this->notifier->sendNotification(
                    UnsubscribeProcess::PROCESS_METHOD_UNSUBSCRIBE,
                    $subscription,
                    $subscriptionPack,
                    $subscription->getUser()->getCarrier()
                );
                $this->onUnsubscribeUpdater->updateSubscriptionByResponse($subscription, $response);

                $user = $subscription->getUser();

                return $response;

            } catch (NotificationSendFailedException $exception) {
                $subscription->setStatus($previousStatus);
                $subscription->setCurrentStage($previousStage);
                throw $exception;
            } finally {
                $this->entitySaveHelper->persistAndSave($subscription);
            }

        } else {
            $parameters = $this->parametersProvider->provideParameters($subscription, $additionalParameters);

            try {
                $response = $this->unsubscribeProcess->doUnsubscribe($parameters);
                $this->onUnsubscribeUpdater->updateSubscriptionByResponse($subscription, $response);

                return $response;

            } catch (UnsubscribingProcessException $exception) {
                $this->logger->debug('Unsubscribe error', [
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode()
                ]);
                $subscription->setStatus(Subscription::IS_ERROR);
                $subscription->setError('unsubscribing_process_exception');
                throw $exception;
            } finally {
                $this->entitySaveHelper->persistAndSave($subscription);
            }
        }
    }

    /**
     * @param Subscription $subscription
     * @param              $response
     */
    public function trackEventsForUnsubscribe(Subscription $subscription, ProcessResult $response)
    {
        $this->subscriptionStatisticSender->trackUnsubscribe(
            $subscription->getUser(),
            $subscription,
            $response
        );
    }


}