<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 11.01.19
 * Time: 10:46
 */

use IdentificationBundle\Entity\CarrierInterface;
use IdentificationBundle\Identification\Common\HeaderEnrichmentHandler;
use IdentificationBundle\Identification\DTO\DeviceData;
use IdentificationBundle\Identification\Handler\HasHeaderEnrichment;
use IdentificationBundle\Identification\Service\IdentificationStatus;
use IdentificationBundle\WifiIdentification\Service\WifiIdentificationDataStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class HeaderEnrichmentHandlerTest extends \PHPUnit\Framework\TestCase
{


    /**
     * @var \Mockery\MockInterface|\IdentificationBundle\Identification\Common\HeaderEnrichmentHandler
     */
    private $headerEnrichmentHandler;
    /**
     * @var \Mockery\MockInterface|\Doctrine\ORM\EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var \Mockery\MockInterface|\IdentificationBundle\Identification\Service\UserFactory
     */
    private $userFactory;
    /**
     * @var \Mockery\MockInterface|\IdentificationBundle\Repository\UserRepository
     */
    private $userRepository;
    private $session;
    /**
     * @var \IdentificationBundle\Identification\Service\IdentificationDataStorage
     */
    private $dataStorage;
    private $identificationStatus;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var CarrierInterface
     */
    private $carrier;
    /**
     * @var HasHeaderEnrichment
     */
    private $handler;

    protected function setUp()/* The :void return type declaration that should be here would cause a BC issue */
    {
        $this->request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->entityManager           = Mockery::spy(\Doctrine\ORM\EntityManagerInterface::class);
        $this->userRepository          = Mockery::spy(\IdentificationBundle\Repository\UserRepository::class);
        $this->userFactory             = Mockery::spy(\IdentificationBundle\Identification\Service\UserFactory::class);
        $this->session                 = new Session(new MockArraySessionStorage());
        $sessionStorage                = new \IdentificationBundle\Identification\Service\Session\SessionStorage($this->session);
        $this->dataStorage             = new \IdentificationBundle\Identification\Service\IdentificationDataStorage($sessionStorage);
        $this->identificationStatus    = new IdentificationStatus($this->dataStorage, new WifiIdentificationDataStorage($sessionStorage));
        $this->headerEnrichmentHandler = new HeaderEnrichmentHandler(
            $this->userFactory,
            $this->entityManager,
            $this->userRepository,
            $this->identificationStatus,
            Mockery::spy(\IdentificationBundle\Identification\Common\PostPaidHandler::class)

        );


        $this->handler = Mockery::spy(HasHeaderEnrichment::class);
        $this->carrier = Mockery::spy(CarrierInterface::class);

        parent::setUp(); // TODO: Change the autogenerated stub
    }


    public function testErrorWhenNoMsisdn()
    {
        $this->expectException(\IdentificationBundle\Identification\Exception\FailedIdentificationException::class);

        $this->headerEnrichmentHandler->process($this->request, $this->handler, $this->carrier, 'token', Mockery::spy(DeviceData::class));
    }

    public function testTokenIsSetForExistingUser()
    {
        $user = new \IdentificationBundle\Entity\User(\ExtrasBundle\Utils\UuidGenerator::generate());

        $this->handler->allows(['getMsisdn' => 'msisdn']);
        $this->userRepository->allows(['findOneByMsisdn' => $user]);

        $this->headerEnrichmentHandler->process($this->request, $this->handler, $this->carrier, 'token', Mockery::spy(DeviceData::class));

        $this->assertEquals('token', $user->getIdentificationToken());
        $this->assertArraySubset(['identification_token' => 'token'], $this->dataStorage->getIdentificationData());

    }

    public function testUserCreatedWhenNoUser()
    {

        $this->handler->allows(['getMsisdn' => 'msisdn']);
        $this->userRepository->allows(['findOneByMsisdn' => null]);

        $this->headerEnrichmentHandler->process($this->request, $this->handler, $this->carrier, 'token', Mockery::spy(DeviceData::class));

        $this->assertArraySubset(['identification_token' => 'token'], $this->dataStorage->getIdentificationData());

        $this->userFactory->shouldHaveReceived('create')->once();

    }
}
