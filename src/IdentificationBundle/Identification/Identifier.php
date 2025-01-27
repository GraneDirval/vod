<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 18:29
 */

namespace IdentificationBundle\Identification;


use IdentificationBundle\Identification\Common\CommonConsentPageFlowHandler;
use IdentificationBundle\Identification\Common\CommonFlowHandler;
use IdentificationBundle\Identification\Common\CommonPassthroughFlowHandler;
use IdentificationBundle\Identification\Common\HeaderEnrichmentHandler;
use IdentificationBundle\Identification\DTO\DeviceData;
use IdentificationBundle\Identification\DTO\IdentifyResult;
use IdentificationBundle\Identification\Handler\ConsentPageFlow\HasConsentPageFlow;
use IdentificationBundle\Identification\Handler\HasCommonFlow;
use IdentificationBundle\Identification\Handler\HasCustomFlow;
use IdentificationBundle\Identification\Handler\HasHeaderEnrichment;
use IdentificationBundle\Identification\Handler\IdentificationHandlerProvider;
use IdentificationBundle\Identification\Handler\PassthroughFlow\HasPassthroughFlow;
use IdentificationBundle\Repository\CarrierRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class Identifier
{
    /**
     * @var IdentificationHandlerProvider
     */
    private $handlerProvider;
    /**
     * @var CarrierRepositoryInterface
     */
    private $carrierRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CommonFlowHandler
     */
    private $commonFlowHandler;
    /**
     * @var \IdentificationBundle\Identification\Common\HeaderEnrichmentHandler
     */
    private $headerEnrichmentHandler;
    /**
     * @var CommonConsentPageFlowHandler
     */
    private $consentPageFlowHandler;
    /**
     * @var CommonPassthroughFlowHandler
     */
    private $passthroughFlowHandler;


    /**
     * Identifier constructor.
     *
     * @param IdentificationHandlerProvider $handlerProvider
     * @param CarrierRepositoryInterface    $carrierRepository
     * @param LoggerInterface               $logger
     * @param CommonFlowHandler             $commonFlowHandler
     * @param HeaderEnrichmentHandler       $headerEnrichmentHandler
     * @param CommonConsentPageFlowHandler  $consentPageFlowHandler
     * @param CommonPassthroughFlowHandler  $passthroughFlowHandler
     */
    public function __construct(
        IdentificationHandlerProvider $handlerProvider,
        CarrierRepositoryInterface $carrierRepository,
        LoggerInterface $logger,
        CommonFlowHandler $commonFlowHandler,
        HeaderEnrichmentHandler $headerEnrichmentHandler,
        CommonConsentPageFlowHandler $consentPageFlowHandler,
        CommonPassthroughFlowHandler $passthroughFlowHandler
    )
    {
        $this->handlerProvider         = $handlerProvider;
        $this->carrierRepository       = $carrierRepository;
        $this->logger                  = $logger;
        $this->commonFlowHandler       = $commonFlowHandler;
        $this->headerEnrichmentHandler = $headerEnrichmentHandler;
        $this->consentPageFlowHandler  = $consentPageFlowHandler;
        $this->passthroughFlowHandler  = $passthroughFlowHandler;
    }

    public function identify(
        int $carrierBillingId,
        Request $request,
        string $token,
        DeviceData $deviceData
    ): IdentifyResult
    {
        $carrier = $this->carrierRepository->findOneByBillingId($carrierBillingId);

        $handler = $this->handlerProvider->get($carrier);

        $this->logger->debug('Resolved handler for identification', [
            'className' => get_class($handler),
            'carrierId' => $carrierBillingId
        ]);

        if ($handler instanceof HasHeaderEnrichment) {
            $this->headerEnrichmentHandler->process($request, $handler, $carrier, $token, $deviceData);
            return new IdentifyResult();

        }
        if ($handler instanceof HasConsentPageFlow) {
            $response = $this->consentPageFlowHandler->process($request, $handler, $carrier, $token);
            return new IdentifyResult($response);

        }
        if ($handler instanceof HasPassthroughFlow) {
            $response = $this->passthroughFlowHandler->process($request, $handler, $carrier, $token);
            return new IdentifyResult($response);

        }
        if ($handler instanceof HasCustomFlow) {
            $handler->process($request);
            return new IdentifyResult();

        }
        if ($handler instanceof HasCommonFlow) {
            $response = $this->commonFlowHandler->process($request, $handler, $token, $carrier);
            return new IdentifyResult($response);

        }

        throw new \RuntimeException('Handlers for identification should have according interfaces');
    }

}