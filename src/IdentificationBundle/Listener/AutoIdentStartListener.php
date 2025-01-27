<?php

namespace IdentificationBundle\Listener;

use CountryCarrierDetectionBundle\Service\Interfaces\ICountryCarrierDetection;
use Doctrine\Common\Annotations\AnnotationReader;
use IdentificationBundle\Controller\Annotation\NoRedirectToWhoops;
use IdentificationBundle\Controller\ControllerWithIdentification;
use IdentificationBundle\Controller\ControllerWithISPDetection;
use IdentificationBundle\Identification\Exception\FailedIdentificationException;
use IdentificationBundle\Identification\Identifier;
use IdentificationBundle\Identification\Service\CarrierResolver;
use IdentificationBundle\Identification\Service\CarrierSelector;
use IdentificationBundle\Identification\Service\DeviceDataProvider;
use IdentificationBundle\Identification\Service\IdentificationStatus;
use IdentificationBundle\Identification\Service\ISPResolver;
use IdentificationBundle\Identification\Service\RouteProvider;
use IdentificationBundle\Identification\Service\Session\IdentificationFlowDataExtractor;
use IdentificationBundle\Identification\Service\TokenGenerator;
use IdentificationBundle\Repository\CarrierRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class AutoIdentStartListener
 */
class AutoIdentStartListener
{
    /**
     * @var ICountryCarrierDetection
     */
    private $carrierDetection;
    /**
     * @var CarrierRepositoryInterface
     */
    private $carrierRepository;

    /**
     * @var Identifier
     */
    private $identifier;
    /**
     * @var TokenGenerator
     */
    private $generator;
    /**
     * @var RouteProvider
     */
    private $routeProvider;
    /**
     * @var IdentificationStatus
     */
    private $identificationStatus;
    /**
     * @var AnnotationReader
     */
    private $annotationReader;
    /**
     * @var CarrierSelector
     */
    private $carrierSelector;
    /**
     * @var DeviceDataProvider
     */
    private $deviceDataProvider;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CarrierResolver
     */
    private $carrierResolver;

    /**
     * AutoIdentStartListener constructor
     *
     * @param ICountryCarrierDetection   $carrierDetection
     * @param CarrierRepositoryInterface $carrierRepository
     * @param Identifier                 $identifier
     * @param TokenGenerator             $generator
     * @param RouteProvider              $routeProvider
     * @param IdentificationStatus       $identificationStatus
     * @param AnnotationReader           $annotationReader
     * @param CarrierSelector            $carrierSelector
     * @param DeviceDataProvider         $deviceDataProvider
     * @param LoggerInterface            $logger
     * @param CarrierResolver            $carrierResolver
     */
    public function __construct(
        ICountryCarrierDetection $carrierDetection,
        CarrierRepositoryInterface $carrierRepository,
        Identifier $identifier,
        TokenGenerator $generator,
        RouteProvider $routeProvider,
        IdentificationStatus $identificationStatus,
        AnnotationReader $annotationReader,
        CarrierSelector $carrierSelector,
        DeviceDataProvider $deviceDataProvider,
        LoggerInterface $logger,
        CarrierResolver $carrierResolver
    )
    {
        $this->carrierDetection     = $carrierDetection;
        $this->carrierRepository    = $carrierRepository;
        $this->identifier           = $identifier;
        $this->generator            = $generator;
        $this->routeProvider        = $routeProvider;
        $this->identificationStatus = $identificationStatus;
        $this->annotationReader     = $annotationReader;
        $this->carrierSelector      = $carrierSelector;
        $this->deviceDataProvider   = $deviceDataProvider;
        $this->logger               = $logger;
        $this->carrierResolver      = $carrierResolver;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $request = $event->getRequest();
        if ($request->isXmlHttpRequest()) {
            return;
        }

        $args = $event->getController();
        if (is_array($args)) {
            $controller = $args[0] ?? null;
            $method     = $args[1] ?? null;
        } else {
            $method = $controller = $args;
        }

        if (!$controller || !$method) {
            return;
        }

        if (!($controller instanceof ControllerWithISPDetection)) {
            return;
        }

        $session   = $request->getSession();
        $ipAddress = $request->getClientIp();
        $carrierId = $this->detectCarrier($ipAddress, $session);

        $this->logger->debug('AutoIdent start', [
            'request'   => $request,
            'ipAddress' => $ipAddress,
            'carrierId' => $carrierId
        ]);

        if (!$carrierId) {
            $response = $this->startWifiFlow($session);
            if ($this->isRedirectToWhoopsRequired($controller, $method)) {
                $event->setController(function () use ($response) {
                    return $response;
                });
                return;
            }
        }

        if (!($controller instanceof ControllerWithIdentification)) {
            $this->logger->debug('Autoident: not instanceof ControllerWithIdentification');
            return;
        }
        if ($this->identificationStatus->isIdentified()) {
            $this->logger->debug('Autoident: isIdentified');
            return;
        }
        if ($this->identificationStatus->isWifiFlowStarted()) {
            $this->logger->debug('Autoident: isWifiFlowStarted');
            return;
        }
        if ($this->identificationStatus->isAlreadyTriedToAutoIdent()) {
            $this->logger->debug('Autoident: isAlreadyTriedToAutoIdent');
            return;
        }

        $this->logger->debug('Autoident: startWifiFlow');
        $this->startWifiFlow($session);
        $this->identificationStatus->registerAutoIdentAttempt();

        $response = $this->doIdentify($request, $carrierId);
        if ($response) {
            $event->setController(function () use ($response) {
                return $response;
            });
        }
    }

    /**
     * @param string           $ipAddress
     * @param SessionInterface $session
     *
     * @return int|null
     */
    private function detectCarrier(string $ipAddress, SessionInterface $session): ?int
    {
        if (!$carrierId = IdentificationFlowDataExtractor::extractBillingCarrierId($session)) {
            $carrierISP = $this->carrierDetection->getCarrier($ipAddress);
            $carrierId  = null;

            $this->logger->debug('Carrier ISP', [
                'ISP' => $carrierISP,
                'user_ip' => $ipAddress
            ]);

            if ($carrierISP) {
                $carrierId = $this->carrierResolver->resolveCarrierByISP($carrierISP);
            }

            if ($carrierId) {
                $this->carrierSelector->selectCarrier($carrierId);
            }
        }

        return $carrierId;
    }

    private function startWifiFlow(SessionInterface $session): Response
    {
        $this->identificationStatus->startWifiFlow();

        return new RedirectResponse($this->routeProvider->getLinkToWifiFlowPage());

    }

    /**
     * @param Request $request
     * @param int     $carrierId
     * @return null|Response
     */
    private function doIdentify(Request $request, int $carrierId): ?Response
    {

        $response = null;
        try {
            $token    = $this->generator->generateToken();
            $result   = $this->identifier->identify(
                (int)$carrierId, $request,
                $token,
                $this->deviceDataProvider->get($request)
            );
            $response = $result->getOverridedResponse();

        } catch (FailedIdentificationException $exception) {

            $this->logger->error('Autoident failed', [
                'message' => $exception->getMessage(),
                'line'    => sprintf('%s:%s', $exception->getCode(), $exception->getLine())
            ]);
            $response = $this->startWifiFlow($request->getSession());
        }

        return $response;
    }

    private function isRedirectToWhoopsRequired(object $controller, string $method): bool
    {
        $controllerReflection = new \ReflectionObject($controller);
        $methodReflection     = $controllerReflection->getMethod($method);

        $annotation = $this->annotationReader->getMethodAnnotation($methodReflection, NoRedirectToWhoops::class);

        return (bool)!$annotation;

    }
}