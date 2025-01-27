<?php declare(strict_types=1);


use IdentificationBundle\BillingFramework\Process\DTO\PinRequestResult;
use IdentificationBundle\Identification\Exception\MissingIdentificationDataException;
use IdentificationBundle\Identification\Service\Session\SessionStorage;
use IdentificationBundle\WifiIdentification\Handler\HasCustomPinVerifyRules;
use IdentificationBundle\WifiIdentification\Handler\WifiIdentificationHandlerInterface;
use IdentificationBundle\WifiIdentification\Service\WifiIdentificationDataStorage;
use IdentificationBundle\WifiIdentification\WifiIdentConfirmator;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class WifiIdentConfirmatorTest extends TestCase
{
    /** @var WifiIdentConfirmator */
    private $wifiIdentConfirmator;

    /** @var IdentificationBundle\WifiIdentification\Handler\WifiIdentificationHandlerProvider | MockInterface */
    private $handlerProvider;

    /** @var IdentificationBundle\WifiIdentification\Common\InternalSMS\PinCodeVerifier | MockInterface */
    private $codeVerifier;

    /** @var IdentificationBundle\Repository\CarrierRepositoryInterface | MockInterface */
    private $carrierRepository;

    /** @var IdentificationBundle\BillingFramework\Process\PinVerifyProcess | MockInterface */
    private $pinVerifyProcess;

    /** @var IdentificationBundle\WifiIdentification\Common\RequestProvider | MockInterface */
    private $requestProvider;

    /** @var IdentificationBundle\WifiIdentification\Service\MsisdnCleaner | MockInterface */
    private $msisdnCleaner;

    /** @var WifiIdentificationDataStorage | MockInterface */
    private $wifiIdentificationDataStorage;

    /** @var IdentificationBundle\WifiIdentification\Service\IdentFinisher | MockInterface */
    private $identFinisher;

    /** @var SubscriptionBundle\Repository\SubscriptionRepository | MockInterface */
    private $subscriptionRepository;

    /** @var IdentificationBundle\Repository\UserRepository | MockInterface */
    private $userRepository;
    private $session;
    private $identificationHandler;

    protected function setUp()
    {

        $this->session               = new Session(new MockArraySessionStorage());
        $this->handlerProvider       = Mockery::spy(IdentificationBundle\WifiIdentification\Handler\WifiIdentificationHandlerProvider::class);
        $this->identificationHandler = Mockery::spy(WifiIdentificationHandlerInterface::class);

        $this->codeVerifier                  = Mockery::spy(IdentificationBundle\WifiIdentification\Common\InternalSMS\PinCodeVerifier::class);
        $this->carrierRepository             = Mockery::spy(IdentificationBundle\Repository\CarrierRepositoryInterface::class);
        $this->pinVerifyProcess              = Mockery::spy(IdentificationBundle\BillingFramework\Process\PinVerifyProcess::class);
        $this->requestProvider               = Mockery::spy(IdentificationBundle\WifiIdentification\Common\RequestProvider::class);
        $this->msisdnCleaner                 = Mockery::spy(IdentificationBundle\WifiIdentification\Service\MsisdnCleaner::class);
        $this->wifiIdentificationDataStorage = new WifiIdentificationDataStorage(new SessionStorage($this->session));
        $this->identFinisher                 = Mockery::spy(IdentificationBundle\WifiIdentification\Service\IdentFinisher::class);
        $this->subscriptionRepository        = Mockery::spy(SubscriptionBundle\Repository\SubscriptionRepository::class);
        $this->userRepository                = Mockery::spy(IdentificationBundle\Repository\UserRepository::class);
        $this->wifiIdentConfirmator          = new WifiIdentConfirmator(
            $this->handlerProvider,
            $this->codeVerifier,
            $this->carrierRepository,
            $this->pinVerifyProcess,
            $this->requestProvider,
            $this->msisdnCleaner,
            $this->wifiIdentificationDataStorage,
            $this->identFinisher,
            $this->subscriptionRepository,
            $this->userRepository,
            Mockery::spy(\IdentificationBundle\Identification\Common\PostPaidHandler::class),
            Mockery::spy(\Symfony\Component\Routing\RouterInterface::class)
        );
    }


    public function testExceptionThrownWhenNoPreviousPinRequest()
    {
        $this->carrierRepository->allows([
            'findOneByBillingId' => Mockery::spy(\CommonDataBundle\Entity\Interfaces\CarrierInterface::class)
        ]);
        $this->handlerProvider->allows([
            'get' => $this->identificationHandler
        ]);
        $this->identificationHandler->allows([
            'getExistingUser' => null
        ]);

        $this->identificationHandler->allows([
            'getPhoneValidationOptions' => new \IdentificationBundle\WifiIdentification\DTO\PhoneValidationOptions('', '')
        ]);


        $this->userRepository->allows([
            'findOneByMsisdn' => null
        ]);
        $this->expectException(MissingIdentificationDataException::class);


        $this->wifiIdentConfirmator->confirm(0, '1234', '123456789', '1237.0.0.1', false, Mockery::spy(\IdentificationBundle\Identification\DTO\DeviceData::class));
    }


    public function testExceptionThrownWhenOTPPinNotValid()
    {
        $this->userRepository->allows([
            'findOneByMsisdn' => null
        ]);
        $pinRequest = new PinRequestResult('123456789', false, []);
        $this->wifiIdentificationDataStorage->setPinRequestResult($pinRequest);
        $this->carrierRepository->allows([
            'findOneByBillingId' => Mockery::spy(\CommonDataBundle\Entity\Interfaces\CarrierInterface::class)
        ]);
        $handler = Mockery::spy(WifiIdentificationHandlerInterface::class);
        $this->handlerProvider->allows([
            'get' => $handler
        ]);
        $handler->allows([
            'getPhoneValidationOptions' => new \IdentificationBundle\WifiIdentification\DTO\PhoneValidationOptions('', '')
        ]);

        $this->codeVerifier->allows([
            'verifyPinCode' => false
        ]);

        $this->expectException(\IdentificationBundle\Identification\Exception\FailedIdentificationException::class);

        $this->wifiIdentConfirmator->confirm(0, '1234', '123456789', '1237.0.0.1', false, Mockery::spy(\IdentificationBundle\Identification\DTO\DeviceData::class));
    }

    public function testArePinVerifyRequestSent()
    {
        $this->userRepository->allows([
            'findOneByMsisdn' => null
        ]);
        $pinRequest = new PinRequestResult('123456789', true, []);
        $this->wifiIdentificationDataStorage->setPinRequestResult($pinRequest);
        $this->carrierRepository->allows([
            'findOneByBillingId' => Mockery::spy(\CommonDataBundle\Entity\Interfaces\CarrierInterface::class)
        ]);
        $handler = Mockery::spy(WifiIdentificationHandlerInterface::class, HasCustomPinVerifyRules::class);
        $this->handlerProvider->allows([
            'get' => $handler
        ]);
        $handler->allows([
            'getPhoneValidationOptions' => new \IdentificationBundle\WifiIdentification\DTO\PhoneValidationOptions('', '')
        ]);


        $this->wifiIdentConfirmator->confirm(0, '1234', '123456789', '1237.0.0.1', false, Mockery::spy(\IdentificationBundle\Identification\DTO\DeviceData::class));

        $this->assertEmpty($this->wifiIdentificationDataStorage->getPinRequestResult());

        $this->pinVerifyProcess->shouldHaveReceived('doPinVerify')->once();
        $handler->shouldHaveReceived('afterSuccessfulPinVerify')->once();
        $handler->shouldHaveReceived('getAdditionalPinVerifyParams')->once();
        $this->identFinisher->shouldHaveReceived('finish')->once();
    }


}
