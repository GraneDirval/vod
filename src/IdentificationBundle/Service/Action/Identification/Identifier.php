<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 08.01.19
 * Time: 18:29
 */

namespace IdentificationBundle\Service\Action\Identification;


use IdentificationBundle\Repository\CarrierRepositoryInterface;
use IdentificationBundle\Service\Action\Identification\Common\CommonFlowHandler;
use IdentificationBundle\Service\Action\Identification\Common\IdentificationFlowDataExtractor;
use IdentificationBundle\Service\Action\Identification\DTO\IdentifyResult;
use IdentificationBundle\Service\Action\Identification\Handler\HasCommonFlow;
use IdentificationBundle\Service\Action\Identification\Handler\HasCustomFlow;
use IdentificationBundle\Service\Action\Identification\Handler\IdentificationHandlerProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
     * Identifier constructor.
     * @param IdentificationHandlerProvider $handlerProvider
     * @param CarrierRepositoryInterface    $carrierRepository
     * @param LoggerInterface               $logger
     * @param CommonFlowHandler             $commonFlowHandler
     */
    public function __construct(
        IdentificationHandlerProvider $handlerProvider,
        CarrierRepositoryInterface $carrierRepository,
        LoggerInterface $logger,
        CommonFlowHandler $commonFlowHandler
    )
    {
        $this->handlerProvider   = $handlerProvider;
        $this->carrierRepository = $carrierRepository;
        $this->logger            = $logger;
        $this->commonFlowHandler = $commonFlowHandler;
    }

    public function identify(int $carrierBillingId, Request $request, string $token, SessionInterface $session): IdentifyResult
    {
        $carrier = $this->carrierRepository->findOneByBillingId($carrierBillingId);

        $handler = $this->handlerProvider->get($carrier);
        $this->logger->debug('Resolved handler for identification', [
            'className' => get_class($handler),
            'carrierId' => $carrierBillingId
        ]);

        if ($handler instanceof HasCustomFlow) {
            $handler->process($request);
            return new IdentifyResult();

        } else if ($handler instanceof HasCommonFlow) {
            $identificationData = $this->storeIdentificationData($session, $token);
            $ispDetectionData   = IdentificationFlowDataExtractor::extractIspDetectionData($session);
            $response           = $this->commonFlowHandler->process(
                $request,
                $handler,
                $identificationData,
                $ispDetectionData,
                $carrier
            );
            return new IdentifyResult($response);

        } else {
            throw new \RuntimeException('Handlers for identification should have according interfaces');
        }

    }

    private function storeIdentificationData(SessionInterface $session, string $token): array
    {
        if ($session->has('identification_data')) {
            $identificationData = $session->get('identification_data');
        } else {
            $identificationData = [];
        }
        $identificationData['identification_token'] = $token;
        $session->set('identification_data', $identificationData);

        return $identificationData;
    }

}