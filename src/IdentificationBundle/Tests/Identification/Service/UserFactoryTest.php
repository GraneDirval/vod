<?php declare(strict_types=1);


use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use IdentificationBundle\User\Service\UserFactory;
use PHPUnit\Framework\TestCase;

class UserFactoryTest extends TestCase
{
    /** @var \IdentificationBundle\User\Service\UserFactory */
    private $userFactory;

    protected function setUp()
    {
        $this->userFactory = new UserFactory(Mockery::spy(\SubscriptionBundle\Subscription\Notification\Common\ShortUrlHashGenerator::class));
    }

    public function testCreate()
    {
        $carrier = Mockery::spy(CarrierInterface::class);

        $user = $this->userFactory->create('msisdn', $carrier, '127.0.0.1', 'token', 'processId' );

        $this->assertEquals('msisdn', $user->getIdentifier());
        $this->assertEquals($carrier, $user->getCarrier());
        $this->assertEquals('127.0.0.1', $user->getIp());
        $this->assertEquals('processId', $user->getIdentificationProcessId());
    }
}
