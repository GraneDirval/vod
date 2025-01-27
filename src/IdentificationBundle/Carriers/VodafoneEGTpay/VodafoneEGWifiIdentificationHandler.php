<?php

namespace IdentificationBundle\Carriers\VodafoneEGTpay;

use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use Doctrine\ORM\NonUniqueResultException;
use ExtrasBundle\Utils\LocalExtractor;
use IdentificationBundle\BillingFramework\ID;
use IdentificationBundle\BillingFramework\Process\DTO\{PinRequestResult, PinVerifyResult};
use IdentificationBundle\BillingFramework\Process\Exception\PinRequestProcessException;
use IdentificationBundle\Entity\User;
use IdentificationBundle\Repository\UserRepository;
use IdentificationBundle\WifiIdentification\DTO\PhoneValidationOptions;
use IdentificationBundle\WifiIdentification\Exception\WifiIdentConfirmException;
use IdentificationBundle\WifiIdentification\Handler\HasConsentPageFlow;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinRequestRules;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinResendRules;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinVerifyRules;
use IdentificationBundle\WifiIdentification\Handler\WifiIdentificationHandlerInterface;
use IdentificationBundle\WifiIdentification\Service\WifiIdentificationDataStorage;
use SubscriptionBundle\Repository\SubscriptionRepository;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class VodafonePKWifiIdentificationHandler
 */
class VodafoneEGWifiIdentificationHandler implements
    WifiIdentificationHandlerInterface,
    HasCustomPinVerifyRules,
    HasCustomPinResendRules,
    HasCustomPinRequestRules,
    HasConsentPageFlow
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var LocalExtractor
     */
    private $localExtractor;

    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository;

    /**
     * @var WifiIdentificationDataStorage
     */
    private $wifiIdentificationDataStorage;

    /**
     * VodafonePKWifiIdentificationHandler constructor
     *
     * @param UserRepository            $userRepository
     * @param  RouterInterface           $router
     * @param LocalExtractor            $localExtractor
     * @param SubscriptionRepository    $subscriptionRepository
     * @param WifiIdentificationDataStorage $wifiIdentificationDataStorage
     */
    public function __construct(
        UserRepository $userRepository,
        RouterInterface $router,
        LocalExtractor $localExtractor,
        SubscriptionRepository $subscriptionRepository,
        WifiIdentificationDataStorage $wifiIdentificationDataStorage
    )
    {
        $this->userRepository            = $userRepository;
        $this->router                    = $router;
        $this->localExtractor            = $localExtractor;
        $this->subscriptionRepository    = $subscriptionRepository;
        $this->wifiIdentificationDataStorage = $wifiIdentificationDataStorage;
    }

    /**
     * @param CarrierInterface $carrier
     *
     * @return bool
     */
    public function canHandle(CarrierInterface $carrier): bool
    {
        return $carrier->getBillingCarrierId() === ID::VODAFONE_EGYPT_TPAY;
    }

    /**
     * @return bool
     */
    public function areSMSSentByBilling(): bool
    {
        return true;
    }

    /**
     * @param string $mobileNumber
     *
     * @return User|null
     */
    public function getExistingUser(string $mobileNumber): ?User
    {
        return $this->userRepository->findOneByMsisdn($this->cleanMsisnd($mobileNumber));
    }

    /**
     * @param string $mobileNumber
     *
     * @return bool
     *
     * @throws NonUniqueResultException
     */
    public function hasActiveSubscription(string $mobileNumber): bool
    {
        $user = $this->getExistingUser($mobileNumber);

        if ($user) {
            $subscription = $this->subscriptionRepository->findCurrentSubscriptionByOwner($user);

            return $subscription && $subscription->isActive();
        }

        return false;
    }

    /**
     * @param PinRequestResult $pinRequestResult
     * @param bool             $isZeroCreditSubAvailable
     *
     * @return array
     */
    public function getAdditionalPinVerifyParams(
        PinRequestResult $pinRequestResult,
        bool $isZeroCreditSubAvailable
    ): array
    {
        $data = $pinRequestResult->getRawData();

        if (empty($data['subscription_contract_id']) || (!$isZeroCreditSubAvailable && empty($data['transactionId']))) {
            throw new WifiIdentConfirmException("Can't process pin verification. Missing required parameters");
        }

        $additionalData = ['client_user' => $data['subscription_contract_id']];

        if (!$isZeroCreditSubAvailable) {
            $additionalData['transactionId'] = $data['transactionId'];
        }

        return $additionalData;
    }

    /**
     * @param PinVerifyResult $pinVerifyResult
     * @param string          $phoneNumber
     *
     * @return string
     */
    public function getMsisdnFromResult(PinVerifyResult $pinVerifyResult, string $phoneNumber): string
    {
        return $this->cleanMsisnd($phoneNumber);
    }

    /**
     * @param PinVerifyResult $pinVerifyResult
     */
    public function afterSuccessfulPinVerify(PinVerifyResult $pinVerifyResult): void
    {
        $this->wifiIdentificationDataStorage->setPinVerifyResult($pinVerifyResult);
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->router->generate('subscription.consent_page_subscribe');
    }

    /**
     * @param PinRequestResult $pinRequestResult
     *
     * @return array
     */
    public function getAdditionalPinResendParameters(PinRequestResult $pinRequestResult): array
    {
        $pinRequestResultData = $pinRequestResult->getRawData();
        $clientUser           = empty($pinRequestResultData['subscription_contract_id'])
            ? null
            : $pinRequestResultData['subscription_contract_id'];

        return ['client_user' => $clientUser];
    }

    /**
     * @return array
     */
    public function getAdditionalPinRequestParams(): array
    {
        return ['lang' => $this->localExtractor->getLocal()];
    }

    /**
     * @param PinRequestProcessException $exception
     *
     * @return string|null
     */
    public function getPinRequestErrorMessage(PinRequestProcessException $exception): ?string
    {
        return $exception->getMessage();
    }

    /**
     * @param \Exception $exception
     */
    public function afterFailedPinVerify(\Exception $exception): void
    {

    }

    public function afterSuccessfulPinRequest(PinRequestResult $result): void
    {
        // TODO: Implement afterSuccessfulPinRequest() method.
    }

    /**
     * @param string $mobileNumber
     *
     * @return string
     */
    private function cleanMsisnd(string $mobileNumber): string
    {
        return str_replace('+', '', $mobileNumber);
    }

    public function getPhoneValidationOptions(): PhoneValidationOptions
    {

        return new PhoneValidationOptions(
            '+201XXXXXXXXX',
            '^\+201[0-9]{9}$',
            'XXXXX',
            '^[0-9][0-9]{5}$'
        );
    }
}