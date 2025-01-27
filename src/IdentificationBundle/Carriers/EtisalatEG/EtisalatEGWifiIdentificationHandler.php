<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 11.01.19
 * Time: 17:17
 */

namespace IdentificationBundle\Carriers\EtisalatEG;


use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use IdentificationBundle\BillingFramework\ID;
use IdentificationBundle\BillingFramework\Process\DTO\PinRequestResult;
use IdentificationBundle\BillingFramework\Process\DTO\PinVerifyResult;
use IdentificationBundle\Entity\User;
use IdentificationBundle\Identification\Service\Session\IdentificationDataStorage;
use IdentificationBundle\Repository\UserRepository;
use IdentificationBundle\WifiIdentification\DTO\PhoneValidationOptions;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinVerifyRules;
use IdentificationBundle\WifiIdentification\Handler\WifiIdentificationHandlerInterface;

class EtisalatEGWifiIdentificationHandler implements
    WifiIdentificationHandlerInterface,
    HasCustomPinVerifyRules

{
    /**
     * @var \IdentificationBundle\Identification\Service\Session\IdentificationDataStorage
     */
    private $dataStorage;
    /**
     * @var UserRepository
     */
    private $repository;


    /**
     * EtisalatEGWifiIdentificationHandler constructor.
     * @param IdentificationDataStorage $dataStorage
     * @param UserRepository            $repository
     */
    public function __construct(IdentificationDataStorage $dataStorage, UserRepository $repository)
    {
        $this->dataStorage = $dataStorage;
        $this->repository  = $repository;
    }

    public function canHandle(CarrierInterface $carrier): bool
    {
        return ID::ETISALAT_EGYPT === $carrier->getBillingCarrierId();
    }


    public function getRedirectUrl()
    {
        // TODO: Implement getRedirectUrl() method.
    }

    public function isPinSendAllowed($mobileNumber): bool
    {
        return true;
    }

    public function areSMSSentByBilling(): bool
    {
        return true;
    }

    public function getAdditionalPinVerifyParams(
        PinRequestResult $pinRequestResult,
        bool $isZeroCreditSubAvailable
    ): array
    {
        $contractId = $pinRequestResult->getRawData()['subscription_contract_id'];

        return [
            'client_user' => $contractId
        ];
    }

    public function afterSuccessfulPinVerify(PinVerifyResult $parameters): void
    {
    }

    public function afterFailedPinVerify(\Exception $exception): void
    {
    }

    public function getExistingUser(string $msisdn): ?User
    {
        return $this->repository->findOneByMsisdn($msisdn);
    }

    public function getMsisdnFromResult(PinVerifyResult $pinVerifyResult, string $phoneNumber): string
    {
        return $phoneNumber;
    }

    public function getPhoneValidationOptions(): PhoneValidationOptions
    {
        return new PhoneValidationOptions(
            '+XXXXXXXXXXXX',
            '^[0-9]{12}$',
            '',
            ''
        );
    }
}