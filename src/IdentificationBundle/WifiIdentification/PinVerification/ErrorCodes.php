<?php


namespace IdentificationBundle\WifiIdentification\PinVerification;


class ErrorCodes
{
    const WRONG_PHONE_NUMBER         = 100;
    const ALREADY_SUBSCRIBED         = 101;
    const PIN_REQUEST_LIMIT_EXCEEDED = 102;
    const INVALID_PIN                = 104;
    const NOT_ENOUGH_CREDIT          = 105;
}