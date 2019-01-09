<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 03.05.18
 * Time: 10:55
 */

namespace SubscriptionBundle\Service\Callback\Common;


use SubscriptionBundle\Exception\SubscriptionException;
use SubscriptionBundle\Service\Callback\Common\Type\AbstractCallbackHandler;

class CallbackTypeHandlerProvider
{
    /**
     * @var \SubscriptionBundle\Service\Callback\Common\Type\AbstractCallbackHandler[]
     */
    private $handlers;


    /**
     * HelperProvider constructor.
     * @param array $handlers
     */
    public function __construct(...$handlers)
    {
        foreach ($handlers as $handler) {

            if (!$handler instanceof AbstractCallbackHandler) {
                throw new \InvalidArgumentException(sprintf('%s is not should be instance of %s', get_class($handler), AbstractCallbackHandler::class));
            }

            $this->handlers[] = $handler;
        }
    }

    public function getHandler($type)
    {
        foreach ($this->handlers as $helper) {
            if ($helper->isSupport($type)) {
                return $helper;
            }
        }
        throw new SubscriptionException("Unsupported helper");
    }
}