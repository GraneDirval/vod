<?php

namespace IdentificationBundle\WifiIdentification;

use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use IdentificationBundle\BillingFramework\Process\Exception\PinRequestProcessException;
use IdentificationBundle\BillingFramework\Process\PinRequestProcess;
use IdentificationBundle\BillingFramework\Process\PinResendProcess;
use IdentificationBundle\Identification\Exception\AlreadyIdentifiedException;
use IdentificationBundle\Identification\Exception\FailedIdentificationException;
use IdentificationBundle\Identification\Service\IdentificationDataStorage;
use IdentificationBundle\Repository\CarrierRepositoryInterface;
use IdentificationBundle\Repository\UserRepository;
use IdentificationBundle\WifiIdentification\Common\InternalSMS\PinCodeSaver;
use IdentificationBundle\WifiIdentification\Common\RequestProvider;
use IdentificationBundle\WifiIdentification\Handler\HasConsentPageFlow;
use IdentificationBundle\WifiIdentification\Handler\HasCustomMsisdnCleaning;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinRequestRules;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinResendRules;
use IdentificationBundle\WifiIdentification\Handler\HasInternalSMSHandling;
use IdentificationBundle\WifiIdentification\Handler\WifiIdentificationHandlerProvider;
use IdentificationBundle\WifiIdentification\Service\MessageComposer;
use IdentificationBundle\WifiIdentification\Service\MsisdnCleaner;
use IdentificationBundle\WifiIdentification\Service\WifiIdentificationDataStorage;
use SubscriptionBundle\BillingFramework\Process\Exception\BillingFrameworkException;

/**
 * Class WifiIdentSMSSender
 */
class WifiIdentSMSSender
{
    /**
     * @var WifiIdentificationHandlerProvider
     */
    private $handlerProvider;
    /**
     * @var CarrierRepositoryInterface
     */
    private $carrierRepository;
    /**
     * @var PinRequestProcess
     */
    private $pinRequestProcess;
    /**
     * @var MessageComposer
     */
    private $messageComposer;
    /**
     * @var MsisdnCleaner
     */
    private $cleaner;
    /**
     * @var PinCodeSaver
     */
    private $pinCodeSaver;
    /**
     * @var RequestProvider
     */
    private $requestProvider;
    /**
     * @var WifiIdentificationDataStorage
     */
    private $wifiIdentificationDataStorage;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var PinResendProcess
     */
    private $pinResendProcess;

    /**
     * WifiIdentSMSSender constructor.
     * @param WifiIdentificationHandlerProvider $handlerProvider
     * @param CarrierRepositoryInterface        $carrierRepository
     * @param PinRequestProcess                 $pinRequestProcess
     * @param MessageComposer                   $messageComposer
     * @param MsisdnCleaner                     $cleaner
     * @param PinCodeSaver                      $pinCodeSaver
     * @param RequestProvider                   $requestProvider
     * @param WifiIdentificationDataStorage     $wifiIdentificationDataStorage
     * @param UserRepository                    $userRepository
     * @param PinResendProcess                  $pinResendProcess
     */
    public function __construct(
        WifiIdentificationHandlerProvider $handlerProvider,
        CarrierRepositoryInterface $carrierRepository,
        PinRequestProcess $pinRequestProcess,
        MessageComposer $messageComposer,
        MsisdnCleaner $cleaner,
        PinCodeSaver $pinCodeSaver,
        RequestProvider $requestProvider,
        WifiIdentificationDataStorage $wifiIdentificationDataStorage,
        UserRepository $userRepository,
        PinResendProcess $pinResendProcess
    )
    {
        $this->handlerProvider   = $handlerProvider;
        $this->carrierRepository = $carrierRepository;
        $this->pinRequestProcess = $pinRequestProcess;
        $this->messageComposer   = $messageComposer;
        $this->cleaner           = $cleaner;
        $this->pinCodeSaver      = $pinCodeSaver;
        $this->requestProvider   = $requestProvider;
        $this->wifiIdentificationDataStorage       = $wifiIdentificationDataStorage;
        $this->userRepository    = $userRepository;
        $this->pinResendProcess  = $pinResendProcess;
    }

    /**
     * @param int    $carrierId
     * @param string $mobileNumber
     * @param bool   $isZeroCreditSubAvailable
     * @param bool   $isResend
     *
     * @throws BillingFrameworkException
     */
    public function sendSMS(
        int $carrierId,
        string $mobileNumber,
        bool $isZeroCreditSubAvailable,
        bool $isResend = false
    ): void
    {
        $carrier = $this->carrierRepository->findOneByBillingId($carrierId);
        $handler = $this->handlerProvider->get($carrier);

        if ($handler instanceof HasConsentPageFlow && $handler->hasActiveSubscription($mobileNumber)) {
            throw new PinRequestProcessException('', 101, '');
        }

        if (!$handler instanceof HasConsentPageFlow && $handler->getExistingUser($mobileNumber)) {
            throw new AlreadyIdentifiedException('User is already identified');
        }

        $validationOptions = $handler->getPhoneValidationOptions();
        if ($phoneRegexPattern = $validationOptions->getPhoneRegexPattern()) {
            $isPinCodeValid = preg_match("/$phoneRegexPattern/", $mobileNumber);
            if (!$isPinCodeValid) {
                throw new FailedIdentificationException(
                    sprintf('Mobile number should be in a `%s` format', $validationOptions->getPhonePlaceholder())
                );
            }
        }

        $pinCode = '000000';
        if (!$handler->areSMSSentByBilling()) {
            $pinCodeObject = $this->pinCodeSaver->savePinCode(mt_rand(0, 99999));
            $pinCode       = $pinCodeObject->getPin();
        }


        if ($handler instanceof HasCustomMsisdnCleaning) {
            $msisdn = $handler->cleanMsisdn($mobileNumber);
        } else {
            $msisdn = $this->cleaner->clean($mobileNumber, $carrier);
        }

        $body   = $this->messageComposer->composePinCodeMessage('_subtext_', 'en', $pinCode);

        if ($isResend && $handler instanceof HasCustomPinResendRules) {
            $this->resendSMS($handler, $carrier, $body);

            return;
        }

        if ($handler instanceof HasCustomPinRequestRules) {
            $additionalParameters = $handler->getAdditionalPinRequestParams();
        } else {
            $additionalParameters = [];
        }

        $parameters = $this->requestProvider->getPinRequestParameters(
            $msisdn,
            $carrier->getBillingCarrierId(),
            $carrier->getOperatorId(),
            $body,
            $additionalParameters,
            $isZeroCreditSubAvailable
        );

        try {
            $result = $this->pinRequestProcess->doPinRequest($parameters);
            $this->wifiIdentificationDataStorage->setPinRequestResult($result);

            if ($handler instanceof HasCustomPinRequestRules) {
                $handler->afterSuccessfulPinRequest($result);
            }

        } catch (PinRequestProcessException $exception) {

            if ($handler instanceof HasCustomPinRequestRules) {
                $handler->getPinRequestErrorMessage($exception);
            }
            throw $exception;
        }
    }

    /**
     * @param HasCustomPinResendRules $handler
     * @param CarrierInterface        $carrier
     * @param string                  $body
     *
     * @throws BillingFrameworkException
     */
    public function resendSMS(HasCustomPinResendRules $handler, CarrierInterface $carrier, string $body)
    {
        $pinRequestResult     = $this->wifiIdentificationDataStorage->getPinRequestResult();
        $additionalParameters = $handler->getAdditionalPinResendParameters($pinRequestResult);

        $parameters = $this->requestProvider->getPinResendParameters(
            $carrier->getBillingCarrierId(),
            $carrier->getOperatorId(),
            $body,
            $additionalParameters
        );

        try {
            $this->pinResendProcess->doPinRequest($parameters);
        } catch (PinRequestProcessException $exception) {
            throw $exception;
        }
    }
}