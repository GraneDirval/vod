<?php

namespace IdentificationBundle\WifiIdentification\PinVerification;

use ExtrasBundle\Utils\LocalExtractor;
use IdentificationBundle\Identification\Service\TranslatorInterface;
use IdentificationBundle\WifiIdentification\PinVerification\ErrorCodeMappers\ErrorCodeMapperProvider;

/**
 * Class ErrorCodeResolver
 */
class ErrorCodeResolver
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var LocalExtractor
     */
    private $localExtractor;
    /**
     * @var ErrorCodeMapperProvider
     */
    private $errorCodeMapperProvider;

    /**
     * ErrorCodeResolver constructor
     *
     * @param TranslatorInterface     $translator
     * @param LocalExtractor          $localExtractor
     * @param ErrorCodeMapperProvider $errorCodeMapperProvider
     */
    public function __construct(
        TranslatorInterface $translator,
        LocalExtractor $localExtractor,
        ErrorCodeMapperProvider $errorCodeMapperProvider
    ) {
        $this->translator              = $translator;
        $this->localExtractor          = $localExtractor;
        $this->errorCodeMapperProvider = $errorCodeMapperProvider;
    }

    /**
     * @param int      $billingResponseCode
     * @param int|null $billingCarrierId
     *
     * @return string
     */
    public function resolveMessage(int $billingResponseCode, int $billingCarrierId = null): string
    {
        $lang = $this->localExtractor->getLocal();

        try {
            $mappedCode = $this->errorCodeMapperProvider->get($billingCarrierId)->map($billingResponseCode);
        } catch (\Throwable $e) {
            $mappedCode = $billingResponseCode;
        }

        switch ($mappedCode) {
            case ErrorCodes::WRONG_PHONE_NUMBER:
                return $this->translator->translate('messages.error.wrong_phone_number', $billingCarrierId, $lang);
                break;

            case ErrorCodes::ALREADY_SUBSCRIBED:
                return $this->translator->translate('messages.error.already_subscribed', $billingCarrierId, $lang);
                break;

            case ErrorCodes::PIN_REQUEST_LIMIT_EXCEEDED:
            case ErrorCodes::REQUEST_LIMIT_EXCEEDED:
                return $this->translator->translate('message.error.pin_request_limit_exceeded', $billingCarrierId, $lang);
                break;

            case ErrorCodes::INVALID_PIN:
                return $this->translator->translate('message.error.invalid_pin', $billingCarrierId, $lang);

            case ErrorCodes::NOT_ENOUGH_CREDIT:
                return $this->translator->translate('messages.error.not_enough_credit', $billingCarrierId, $lang);

            case ErrorCodes::BLACKLISTED_PHONE_NUMBER:
                return $this->translator->translate('messages.error.wifi_blacklisted', $billingCarrierId, $lang);

            default:
                return $this->translator->translate('whoops.short', $billingCarrierId, $lang);
                break;
        }

    }

}