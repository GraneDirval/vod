<?php

namespace IdentificationBundle\Twig;

use App\Domain\Entity\Carrier;
use App\Domain\Repository\CarrierRepository;
use IdentificationBundle\Identification\Handler\ConsentPageFlow\HasConsentPageFlow;
use IdentificationBundle\Identification\Handler\IdentificationHandlerProvider;
use IdentificationBundle\Identification\Service\IdentificationDataStorage;
use IdentificationBundle\Identification\Service\IdentificationFlowDataExtractor;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Class IdentificationStatusExtension
 */
class IdentificationStatusExtension extends AbstractExtension
{
    /**
     * @var IdentificationDataStorage
     */
    private $dataStorage;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var CarrierRepository
     */
    private $carrierRepository;
    /**
     * @var IdentificationHandlerProvider
     */
    private $identificationHandlerProvider;

    /**
     * IdentificationStatusExtension constructor.
     *
     * @param IdentificationDataStorage $dataStorage
     * @param SessionInterface $session
     * @param CarrierRepository $carrierRepository
     * @param IdentificationHandlerProvider $identificationHandlerProvider
     */
    public function __construct(
        IdentificationDataStorage $dataStorage,
        SessionInterface $session,
        CarrierRepository $carrierRepository,
        IdentificationHandlerProvider $identificationHandlerProvider
    ) {
        $this->dataStorage = $dataStorage;
        $this->session = $session;
        $this->carrierRepository = $carrierRepository;
        $this->identificationHandlerProvider = $identificationHandlerProvider;
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('isCarrierDetected', [$this, 'isCarrierDetected']),

            new TwigFunction('getCarrierId', [$this, 'getCarrierId']),

            new TwigFunction('isIdentified', function () {
                $identificationData = $this->dataStorage->readIdentificationData();
                return isset($identificationData['identification_token']) && $identificationData['identification_token'];
            }),

            new TwigFunction('isConsentFlow', function () {
                $ispDetectionData = IdentificationFlowDataExtractor::extractIspDetectionData($this->session);
                $carrierId =  empty($ispDetectionData['carrier_id']) ? null : (int) $ispDetectionData['carrier_id'];
                $isConsent = null;

                if ($carrierId) {
                    $identHandler = $this->identificationHandlerProvider->get($this->carrierRepository->findOneByBillingId($carrierId));
                    $isConsent =  $identHandler instanceof HasConsentPageFlow;
                } else {
                    $isConsent = (bool) $this->dataStorage->readValue('consentFlow[token]');
                }

                return $isConsent;
            }),

            new TwigFunction('isWifiFlow', function () {
                return (bool)$this->dataStorage->readValue('is_wifi_flow');
            }),

            new TwigFunction('getIdentificationToken', function () {
                $identificationData = $this->dataStorage->readIdentificationData();
                return $identificationData['identification_token'] ?? null;
            }),

            new TwigFunction('isOtp', [$this, 'isOtp']),
        ];
    }

    /**
     * @return bool
     */
    public function isCarrierDetected(): bool
    {
        $ispDetectionData = IdentificationFlowDataExtractor::extractIspDetectionData($this->session);
        return isset($ispDetectionData['carrier_id']) && $ispDetectionData['carrier_id'];
    }

    /**
     * @return int|null
     */
    public function getCarrierId(): ?int
    {
        $ispDetectionData = IdentificationFlowDataExtractor::extractIspDetectionData($this->session);
        return empty($ispDetectionData['carrier_id']) ? null : (int) $ispDetectionData['carrier_id'];
    }

    /**
     * @return bool
     */
    public function isOtp(): bool
    {
        $ispDetectionData = IdentificationFlowDataExtractor::extractIspDetectionData($this->session);
        if (isset($ispDetectionData['carrier_id']) && $ispDetectionData['carrier_id']) {
            /** @var Carrier $carrier */
            $carrier = $this->carrierRepository->findOneByBillingId($ispDetectionData['carrier_id']);
            return $carrier->isConfirmationClick();
        }
        return false;
    }
}