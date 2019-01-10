<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 07.05.18
 * Time: 10:25
 */

namespace IdentificationBundle\Service\Action\Identification\Common;


use SubscriptionBundle\BillingFramework\Process\API\DTO\ProcessRequestParameters;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class RequestParametersProvider
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    /**
     * @var RouterInterface
     */
    private $router;


    /**
     * BillingFrameworkHelperService constructor.
     * @param EventDispatcherInterface $eventDispatcher
     * @param RouterInterface          $router
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        RouterInterface $router
    )
    {

        $this->eventDispatcher = $eventDispatcher;
        $this->router          = $router;
    }

    /**
     * @param array  $identificationData
     * @param string $clientIp
     * @param string $redirectUrl
     * @param array  $headers
     * @param array  $additionalData
     * @return ProcessRequestParameters
     */
    public function prepareRequestParameters(
        array $identificationData,
        string $clientIp,
        string $redirectUrl,
        array $headers = [],
        array $additionalData = []
    ): ProcessRequestParameters
    {


        $parameters               = new ProcessRequestParameters();
        $parameters->listener     = $this->router->generate('identification_callback_listenidentify', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $parameters->client       = 'store';
        $parameters->listenerWait = $this->router->generate('identification_callback_listenidentify', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $parameters->clientId     = $identificationData['identification_token'];
        $parameters->carrier      = $identificationData['carrier_id'];
        $parameters->userIp       = $clientIp;
        $parameters->redirectUrl  = $redirectUrl;

        // The request headers of the end user.
        $currentUserRequestHeaders = '';
        foreach ($headers as $key => $value) {
            $currentUserRequestHeaders .= "{$key}: {$value[0]}\r\n";
        }
        $parameters->userHeaders    = $currentUserRequestHeaders;
        $parameters->additionalData = $additionalData;

        return $parameters;
    }
}