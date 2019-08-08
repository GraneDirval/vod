<?php

namespace App\Controller;

use App\CarrierTemplate\TemplateConfigurator;
use App\Domain\ACL\Exception\AccessException;
use App\Domain\ACL\LandingPageACL;
use App\Domain\Entity\Campaign;
use App\Domain\Entity\Carrier;
use App\Domain\Repository\CampaignRepository;
use App\Domain\Service\CarrierOTPVerifier;
use App\Domain\Service\Piwik\ContentStatisticSender;
use IdentificationBundle\Controller\ControllerWithISPDetection;
use IdentificationBundle\Entity\CarrierInterface;
use IdentificationBundle\Identification\Exception\MissingCarrierException;
use IdentificationBundle\Identification\Service\CarrierSelector;
use IdentificationBundle\Identification\Service\Session\IdentificationFlowDataExtractor;
use IdentificationBundle\Identification\Service\PassthroughChecker;
use IdentificationBundle\Repository\CarrierRepositoryInterface;
use IdentificationBundle\WifiIdentification\Service\WifiIdentificationDataStorage;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use SubscriptionBundle\Affiliate\Service\AffiliateVisitSaver;
use SubscriptionBundle\Controller\Traits\ResponseTrait;
use SubscriptionBundle\Service\CAPTool\Exception\CapToolAccessException;
use SubscriptionBundle\Service\CAPTool\Exception\SubscriptionCapReachedOnAffiliate;
use SubscriptionBundle\Service\CAPTool\Exception\SubscriptionCapReachedOnCarrier;
use SubscriptionBundle\Service\CAPTool\Exception\VisitCapReached;
use SubscriptionBundle\Service\CAPTool\SubscriptionLimiter;
use SubscriptionBundle\Service\CAPTool\SubscriptionLimitNotifier;
use SubscriptionBundle\Service\SubscribeUrlResolver;
use SubscriptionBundle\Service\VisitCAPTool\ConstraintAvailabilityChecker;
use SubscriptionBundle\Service\VisitCAPTool\VisitNotifier;
use SubscriptionBundle\Service\VisitCAPTool\VisitTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class LPController
 */
class LPController extends AbstractController implements ControllerWithISPDetection, AppControllerInterface
{
    use ResponseTrait;

    /**
     * @var ContentStatisticSender
     */
    private $contentStatisticSender;
    /**
     * @var CampaignRepository
     */
    private $campaignRepository;
    /**
     * @var string
     */
    private $imageBaseUrl;
    /**
     * @var LandingPageACL
     */
    private $landingPageAccessResolver;
    /**
     * @var CarrierOTPVerifier
     */
    private $OTPVerifier;
    /**
     * @var string
     */
    private $defaultRedirectUrl;
    /**
     * @var TemplateConfigurator
     */
    private $templateConfigurator;
    /**
     * @var WifiIdentificationDataStorage
     */
    private $wifiIdentificationDataStorage;
    /**
     * @var SubscriptionLimiter
     */
    private $limiter;
    /**
     * @var CarrierRepositoryInterface
     */
    private $carrierRepository;
    /**
     * @var SubscriptionLimitNotifier
     */
    private $subscriptionLimitNotifier;
    /**
     * @var VisitTracker
     */
    private $visitTracker;
    /**
     * @var VisitNotifier
     */
    private $visitNotifier;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CarrierSelector
     */
    private $carrierSelector;
    /**
     * @var SubscribeUrlResolver
     */
    private $subscribeUrlResolver;
    /**
     * @var PassthroughChecker
     */
    private $passthroughChecker;
    /**
     * @var ConstraintAvailabilityChecker
     */
    private $visitConstraintChecker;

    /**
     * LPController constructor.
     *
     * @param ContentStatisticSender     $contentStatisticSender
     * @param CampaignRepository         $campaignRepository
     * @param LandingPageACL             $landingPageAccessResolver
     * @param string                     $imageBaseUrl
     * @param CarrierOTPVerifier         $OTPVerifier
     * @param string                     $defaultRedirectUrl
     * @param TemplateConfigurator       $templateConfigurator
     * @param WifiIdentificationDataStorage $wifiIdentificationDataStorage
     * @param SubscriptionLimiter $limiter
     * @param SubscriptionLimitNotifier $subscriptionLimitNotifier
     * @param CarrierRepositoryInterface $carrierRepository
     * @param VisitTracker $visitTracker
     * @param VisitNotifier $notifier
     * @param LoggerInterface $logger
     * @param CarrierSelector $carrierSelector
     * @param SubscribeUrlResolver $subscribeUrlResolver
     */
    public function __construct(
        ContentStatisticSender $contentStatisticSender,
        CampaignRepository $campaignRepository,
        LandingPageACL $landingPageAccessResolver,
        string $imageBaseUrl,
        CarrierOTPVerifier $OTPVerifier,
        string $defaultRedirectUrl,
        TemplateConfigurator $templateConfigurator,
        WifiIdentificationDataStorage $wifiIdentificationDataStorage,
        SubscriptionLimiter $limiter,
        SubscriptionLimitNotifier $subscriptionLimitNotifier,
        CarrierRepositoryInterface $carrierRepository,
        VisitTracker $visitTracker,
        VisitNotifier $notifier,
        LoggerInterface $logger,
        CarrierSelector $carrierSelector,
        SubscribeUrlResolver $subscribeUrlResolver,
        PassthroughChecker $passthroughChecker
        SubscribeUrlResolver $subscribeUrlResolver,
        ConstraintAvailabilityChecker $visitConstraintChecker
    )
    {
        $this->contentStatisticSender        = $contentStatisticSender;
        $this->campaignRepository            = $campaignRepository;
        $this->landingPageAccessResolver     = $landingPageAccessResolver;
        $this->imageBaseUrl                  = $imageBaseUrl;
        $this->OTPVerifier                   = $OTPVerifier;
        $this->defaultRedirectUrl            = $defaultRedirectUrl;
        $this->templateConfigurator          = $templateConfigurator;
        $this->wifiIdentificationDataStorage = $wifiIdentificationDataStorage;
        $this->limiter                       = $limiter;
        $this->carrierRepository             = $carrierRepository;
        $this->subscriptionLimitNotifier     = $subscriptionLimitNotifier;
        $this->visitTracker                  = $visitTracker;
        $this->visitNotifier                 = $notifier;
        $this->logger                        = $logger;
        $this->carrierSelector               = $carrierSelector;
        $this->subscribeUrlResolver          = $subscribeUrlResolver;
        $this->passthroughChecker        = $passthroughChecker;
        $this->visitConstraintChecker        = $visitConstraintChecker;
    }


    /**
     * @\IdentificationBundle\Controller\Annotation\NoRedirectToWhoops
     * @Route("/lp",name="landing")
     *
     * @param Request $request
     *
     * @return Response
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function landingPageAction(Request $request)
    {
        $session        = $request->getSession();
        $campaignBanner = null;
        $background     = null;

        $cid      = $request->get('cid', '');
        $campaign = $this->resolveCampaignFromRequest($cid);
        if ($cid && !$campaign) {
            return RedirectResponse::create($this->defaultRedirectUrl);
        }

        /** @var Campaign $campaign */
        if ($campaign) {
            // Useless method atm.
            AffiliateVisitSaver::saveCampaignId($cid, $session);

            if ($this->landingPageAccessResolver->isAffiliatePublisherBanned($request, $campaign)) {
                return new RedirectResponse($this->defaultRedirectUrl);
            }

            $campaignBanner = $this->imageBaseUrl . '/' . $campaign->getImagePath();
            $background     = $campaign->getBgColor();
        }

        $carrier = $this->resolveCarrierFromRequest($request);
        $this->checkClickableSubImage($carrier, $campaign);
        if ($carrier && $campaign) {
            $this->logger->debug('Start CAP checking', ['carrier' => $carrier]);
            try {
                $this->landingPageAccessResolver->ensureCanAccess($campaign, $carrier);

            } catch (SubscriptionCapReachedOnCarrier $e) {
                $this->logger->debug('CAP checking throw SubscriptionCapReachedOnCarrier');
                $this->subscriptionLimitNotifier->notifyLimitReachedForCarrier($e->getCarrier());
                return RedirectResponse::create($this->defaultRedirectUrl);

            } catch (SubscriptionCapReachedOnAffiliate $e) {
                $this->logger->debug('CAP checking throw SubscriptionCapReachedOnAffiliate');
                $this->subscriptionLimitNotifier->notifyLimitReachedByAffiliate($e->getConstraint(), $e->getCarrier());
                return RedirectResponse::create($this->defaultRedirectUrl);

            } catch (VisitCapReached $exception) {
                $this->logger->debug('CAP checking throw VisitCapReached');
                $this->visitNotifier->notifyLimitReached($exception->getConstraint(), $carrier);
                return RedirectResponse::create($this->defaultRedirectUrl);

            } catch (CapToolAccessException | AccessException $exception) {
                $this->logger->debug('CAP checking throw Access Exception');
                return RedirectResponse::create($this->defaultRedirectUrl);
            }

            if ($this->visitConstraintChecker->isCapEnabledForAffiliate($campaign->getAffiliate())) {
                $this->visitTracker->trackVisit($carrier, $campaign, $session->getId());
            }
            $this->logger->debug('Finish CAP checking');
        }

        AffiliateVisitSaver::savePageVisitData($session, $request->query->all());

        $billingCarrierId = IdentificationFlowDataExtractor::extractBillingCarrierId($session);

        $this->contentStatisticSender->trackVisit($session);

        if ($carrier
            && !(bool)$this->wifiIdentificationDataStorage->isWifiFlow()
            && $this->landingPageAccessResolver->isLandingDisabled($request)
        ) {
            return new RedirectResponse($this->subscribeUrlResolver->getSubscribeRoute($carrier));
        }

        if (!$cid) {
            $this->OTPVerifier->forceWifi($session);
        }

        $template = $this->templateConfigurator->getTemplate('landing', (int)$billingCarrierId);

        return $this->render($template, [
            'campaignBanner' => $campaignBanner,
            'background'     => $background
        ]);
    }

    /**
     * @Route("/lp/select-carrier-wifi", name="select_carrier_wifi")
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function handleCarrierSelect(Request $request)
    {
        if (!$billingCarrierId = $request->get('carrier_id', '')) {
            $this->carrierSelector->removeCarrier();

            return $this->getSimpleJsonResponse('');
        }

        try {
            $this->carrierSelector->selectCarrier((int)$billingCarrierId);
            $offerTemplate = $this->templateConfigurator->getTemplate('landing_offer', $billingCarrierId);
            $carrier       = $this->carrierRepository->findOneByBillingId($billingCarrierId);

            $data = [
                'success'     => true,
                'annotation'  => $this->renderView('@App/Components/Ajax/annotation.html.twig'),
                'offer'       => $this->renderView($offerTemplate),
                'passthrough' => $this->passthroughChecker->isCarrierPassthrough($carrier)
            ];

            return $this->getSimpleJsonResponse('Successfully selected', 200, [], $data);
        } catch (MissingCarrierException $exception) {
            return $this->getSimpleJsonResponse($exception->getMessage(), 200, [], ['success' => false]);
        }
    }

    /**
     * @Method("POST")
     * @Route("/after_carrier_selected", name="ajax_after_carrier_selected")
     * @return JsonResponse
     */
    public function ajaxAfterCarrierSelected(Request $request)
    {

        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }


        try {
            $session  = $request->getSession();
            $cid      = $session->get('campaign_id', '');
            $carrier  = $this->resolveCarrierFromRequest($request);
            $campaign = $this->resolveCampaignFromRequest($cid);

            if (!$campaign) {
                return $this->getSimpleJsonResponse('success', 200, [], [
                    'success' => true,
                ]);
            }


            try {
                $this->landingPageAccessResolver->ensureCanAccessByVisits($campaign, $carrier);
            } catch (VisitCapReached $capReached) {
                return $this->getSimpleJsonResponse('success', 200, [], [
                    'success'     => false,
                    'redirectUrl' => $this->defaultRedirectUrl
                ]);
            }

            $this->visitTracker->trackVisit($carrier, $campaign, $session->getId());


            return $this->getSimpleJsonResponse('success', 200, [], [
                'success' => true,
            ]);

        } catch (\Exception $exception) {
            return $this->getSimpleJsonResponse('success', 500, [], [
                'success' => false,
                'error'   => $exception->getMessage()
            ]);
        }
    }

    /**
     * @param Request $request
     *
     * @return Carrier|null
     */
    private function resolveCarrierFromRequest(Request $request): ?CarrierInterface
    {
        $billingCarrierId = IdentificationFlowDataExtractor::extractBillingCarrierId($request->getSession());

        if (!empty($billingCarrierId)) {
            return $this->carrierRepository->findOneByBillingId($billingCarrierId);
        }

        return null;
    }

    /**
     * @param $cid
     * @return Campaign|null
     */
    private function resolveCampaignFromRequest($cid): ?Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->campaignRepository->findOneBy([
            'campaignToken' => $cid,
            'isPause'       => false
        ]);

        return $campaign ?? null;
    }

    /**
     * TODO: Transfer to separate class for additional session data
     *
     * @param Carrier|null  $carrier
     * @param Campaign|null $campaign
     */
    public function checkClickableSubImage(Carrier $carrier = null, Campaign $campaign = null)
    {
        //is_clickable_sub_image has default value - true
        //1.Highest priority in carrier that has value not equal the default
        //2.Next if the campaign has value not equal the default
        //3.All other situations
//        if ($carrier !== null && $carrier->isClickableSubImage() === false) {
//            $this->dataStorage->storeIsClickableSubImage(false);
//        } else if ($carrier !== null && $carrier->isClickableSubImage() === true
//            && $campaign !== null && $campaign->isClickableSubImage() === false) {
//            $this->dataStorage->storeIsClickableSubImage(false);
//        } else {
//            $this->dataStorage->storeIsClickableSubImage(true);
//        }
    }
}