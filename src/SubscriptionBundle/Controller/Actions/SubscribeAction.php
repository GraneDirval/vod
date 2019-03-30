<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 30.04.18
 * Time: 10:30
 */

namespace SubscriptionBundle\Controller\Actions;


use ExtrasBundle\Utils\UrlParamAppender;
use IdentificationBundle\Identification\DTO\IdentificationData;
use IdentificationBundle\Identification\DTO\ISPData;
use IdentificationBundle\Identification\Handler\HasConsentPageFlow;
use IdentificationBundle\Identification\Handler\IdentificationHandlerProvider;
use IdentificationBundle\Identification\Service\IdentificationDataStorage;
use IdentificationBundle\Repository\CarrierRepositoryInterface;
use Psr\Log\LoggerInterface;
use SubscriptionBundle\Controller\Traits\ResponseTrait;
use SubscriptionBundle\Exception\ExistingSubscriptionException;
use SubscriptionBundle\Service\Action\Subscribe\Common\BlacklistVoter;
use SubscriptionBundle\Service\Action\Subscribe\Common\CommonFlowHandler;
use SubscriptionBundle\Service\Action\Subscribe\Handler\HasCustomFlow;
use SubscriptionBundle\Service\Action\Subscribe\Handler\SubscriptionHandlerProvider;
use SubscriptionBundle\Service\CapConstraint\SubscriptionConstraintByCarrier;
use SubscriptionBundle\Service\UserExtractor;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Router;

class SubscribeAction extends Controller
{
    use ResponseTrait;

    /**
     * @var UserExtractor
     */
    private $userExtractor;
    /**
     * @var CommonFlowHandler
     */
    private $commonFlowHandler;
    /**
     * @var Router
     */
    private $router;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var BlacklistVoter
     */
    private $blacklistVoter;
    /**
     * @var UrlParamAppender
     */
    private $urlParamAppender;
    /**
     * @var SubscriptionHandlerProvider
     */
    private $handlerProvider;
    /**
     * @var IdentificationDataStorage
     */
    private $identificationDataStorage;
    /**
     * @var IdentificationHandlerProvider
     */
    private $identificationHandlerProvider;
    /**
     * @var CarrierRepositoryInterface
     */
    private $carrierRepository;
    /**
     * @var SubscriptionConstraintByCarrier
     */
    private $subscriptionConstraintByCarrier;

    /**
     * SubscribeAction constructor.
     *
     * @param UserExtractor                   $userExtractor
     * @param CommonFlowHandler               $commonFlowHandler
     * @param Router                          $router
     * @param LoggerInterface                 $logger
     * @param UrlParamAppender                $urlParamAppender
     * @param SubscriptionHandlerProvider     $handlerProvider
     * @param BlacklistVoter                  $blacklistVoter
     * @param IdentificationDataStorage       $identificationDataStorage
     * @param IdentificationHandlerProvider   $identificationHandlerProvider
     * @param CarrierRepositoryInterface      $carrierRepository
     * @param SubscriptionConstraintByCarrier $subscriptionConstraintByCarrier
     */
    public function __construct(
        UserExtractor $userExtractor,
        CommonFlowHandler $commonFlowHandler,
        Router $router,
        LoggerInterface $logger,
        UrlParamAppender $urlParamAppender,
        SubscriptionHandlerProvider $handlerProvider,
        BlacklistVoter $blacklistVoter,
        IdentificationDataStorage $identificationDataStorage,
        IdentificationHandlerProvider $identificationHandlerProvider,
        CarrierRepositoryInterface $carrierRepository,
        SubscriptionConstraintByCarrier $subscriptionConstraintByCarrier
    )
    {
        $this->userExtractor                   = $userExtractor;
        $this->commonFlowHandler               = $commonFlowHandler;
        $this->router                          = $router;
        $this->logger                          = $logger;
        $this->urlParamAppender                = $urlParamAppender;
        $this->handlerProvider                 = $handlerProvider;
        $this->blacklistVoter                  = $blacklistVoter;
        $this->identificationDataStorage       = $identificationDataStorage;
        $this->identificationHandlerProvider   = $identificationHandlerProvider;
        $this->carrierRepository               = $carrierRepository;
        $this->subscriptionConstraintByCarrier = $subscriptionConstraintByCarrier;
    }

    /**
     * @param Request            $request
     * @param IdentificationData $identificationData
     * @return \Symfony\Component\HttpFoundation\JsonResponse|RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws ExistingSubscriptionException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \SubscriptionBundle\Exception\ActiveSubscriptionPackNotFound
     */
    public function __invoke(Request $request, IdentificationData $identificationData, ISPData $ISPData)
    {
        $constraintByCarrierResult = $this->subscriptionConstraintByCarrier->handleRequest();

        if ($constraintByCarrierResult) {
            return $constraintByCarrierResult;
        }

        /*if ($result = $this->handleRequestByLegacyService($request)) {
            return $result;
        }*/

        $this->ensureNotConsentPageFlow($ISPData->getCarrierId());

        if ($result = $this->blacklistVoter->checkIfSubscriptionRestricted($request)) {
            return $result;
        }

        $user = $this->userExtractor->getUserByIdentificationData($identificationData);


        try {

            $subscriber = $this->handlerProvider->getSubscriber($user->getCarrier());
            if ($subscriber instanceof HasCustomFlow) {
                return $subscriber->process($request, $request->getSession(), $user);
            } else {
                return $this->commonFlowHandler->process($request, $user);
            }
        } catch (ExistingSubscriptionException $exception) {
            return new RedirectResponse($this->generateUrl('index', ['err_handle' => 'already_subscribed']));
        }


    }

    private function ensureNotConsentPageFlow(int $carrierId): void
    {
        $carrier = $this->carrierRepository->findOneByBillingId($carrierId);

        $handler = $this->identificationHandlerProvider->get($carrier);

        if ($handler instanceof HasConsentPageFlow) {
            throw new BadRequestHttpException('This action is not available for `ConsentPageFlow`');
        }

    }

    /*private function handleRequestByLegacyService(Request $request)
    {
        if ($this->mobimindService->isMobimind($request)) {
            return $this->mobimindService->processRequest($request);
        }

        if ($this->mobilifeService->isMobilife($request)) {
            return $this->mobilifeService->processRequest($request);
        }

        $carrier = $request->get('carrier');
        if ($this->megasystCarrierChecker->isMegasystCarrierId($carrier)) {

            if ($phone = $request->get('phone')) {
                return new JsonResponse($this->megasystPhoneChecker->checkPhone($phone, $carrier));
            } else {
                return new Response(200);
            }
        }


    }*/


}