<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 03.01.19
 * Time: 16:55
 */

namespace App\API\Listener;


use App\API\Utils\ResponseMaker;
use App\API\Controller\APIControllerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class APIJsonEncodeListener implements EventSubscriberInterface
{


    /**
     * APIJsonEncodeListener constructor.
     */
    public function __construct()
    {
    }


    public function onException(GetResponseForExceptionEvent $event)
    {
        $pathinfo = $event->getRequest()->getPathInfo();


        if (strpos($pathinfo, '/api', 0) !== false) {

            $exception = $event->getException();

            if ($exception instanceof HttpException) {
                $code = $exception->getStatusCode();
            } else {
                $code = 500;
            }

            $event->setResponse(ResponseMaker::makeErrorResponse('error', $code, $exception->getMessage()));

        }
    }


    public function onKernelController(FilterControllerEvent $event)
    {

        $controller = $event->getController()[1] ?? null;
        if (!$controller || !($controller instanceof APIControllerInterface)) {
            return;
        }

        $request     = $event->getRequest();
        $contentType = $request->headers->get('Content-Type');

        if (strtolower($contentType) !== strtolower('application/json')) {
            throw new BadRequestHttpException('Content-Type should be `application/json`');
        }


        $encoder = new JsonEncoder();

        $request->request->replace($encoder->decode($request->getContent(), 'array'));


    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2')))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION  => 'onException'
        ];
    }
}