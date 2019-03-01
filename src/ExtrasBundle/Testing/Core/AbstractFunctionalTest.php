<?php

namespace ExtrasBundle\Testing\Core;


use Doctrine\Common\DataFixtures\Executor\AbstractExecutor;
use GuzzleHttp\Client;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

abstract class AbstractFunctionalTest extends \Liip\FunctionalTestBundle\Test\WebTestCase
{
    use MockeryPHPUnitIntegration;
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * @var AbstractExecutor
     */
    private $fixtures;

    abstract protected function initializeServices(ContainerInterface $container);


    abstract protected function getFixturesListLoadedForEachTest(): array;

    /**
     * {@inheritDoc}
     */
    final protected function setUp()
    {

        self::bootKernel();

        $container = self::$kernel->getContainer();

        $this->entityManager = $container->get('doctrine.orm.entity_manager');

        $this->mockSymfonyServices($container);

        $this->initializeServices($container);


        $finalFixtures = $this->getFixturesListLoadedForEachTest();
        if (count($finalFixtures)) {
            $this->fixtures = $this->loadFixtures(
                $finalFixtures,
                false,
                null,
                'doctrine',
                true
            );
        }


    }

    protected function makeClient($authentication = false, array $params = []): \Symfony\Bundle\FrameworkBundle\Client
    {
        $client = parent::makeClient($authentication, $params); // TODO: Change the autogenerated stub

        $container = $client->getContainer();

        $clientMock =
            Mockery::spy(Client::class)
                ->shouldReceive('request', 'requestAsync')
                ->andThrow(
                    new \BadMethodCallException(
                        "Please don't use real billing api requests for automated testing. \nOverride `subscription.http.client` service by according stub/mock in `configureWebClientContainer` method if you need to simulate request"
                    )
                )
                ->getMock();

        Self::$container->set('subscription.http.client', $clientMock);

        $this->configureWebClientClientContainer($container);

        $this->configureMockedSymfonyServices($container);

        return $client;
    }


    abstract protected function configureWebClientClientContainer(ContainerInterface $container);

    final protected function tearDown()
    {
        /*$this->entityManager->rollback();*/
    }

    final protected function getObjectFromFixture($reference)
    {
        return $this->fixtures->getReferenceRepository()->getReference($reference);
    }

    final protected function performFixtureChange(callable $callback)
    {
        $callback();

        $this->fixtures->getReferenceRepository()->getManager()->flush();
    }


    /**
     * @var Request
     */
    protected $request;
    /**
     * @var RequestStack|Mockery\MockInterface
     */
    protected $requestStack;
    /**
     * @var SessionInterface
     */
    protected $session;


    public function mockSymfonyServices(ContainerInterface $container)
    {

        $this->request      = new Request();
        $this->requestStack = Mockery::spy(RequestStack::class)->makePartial();
        $this->requestStack->allows(['getCurrentRequest' => $this->request]);
        $this->session = new Session(new MockArraySessionStorage());
        $this->request->setSession($this->session);
    }

    private function configureMockedSymfonyServices(ContainerInterface $container)
    {
        $container->set('request_stack', $this->requestStack);
        $container->set('session', $this->session);
    }


}