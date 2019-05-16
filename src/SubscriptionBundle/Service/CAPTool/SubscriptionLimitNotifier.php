<?php


namespace SubscriptionBundle\Service\CAPTool;


use IdentificationBundle\Entity\CarrierInterface;
use SubscriptionBundle\Entity\Affiliate\ConstraintByAffiliate;
use SubscriptionBundle\Service\EntitySaveHelper;
use SubscriptionBundle\Service\Notification\Email\CAPNotificationSender;

class SubscriptionLimitNotifier
{
    /**
     * @var CAPNotificationSender
     */
    private $notificationSender;
    /**
     * @var EntitySaveHelper
     */
    private $entitySaveHelper;

    public function __construct(CAPNotificationSender $notificationSender, EntitySaveHelper $entitySaveHelper)
    {
        $this->notificationSender = $notificationSender;
        $this->entitySaveHelper   = $entitySaveHelper;
    }

    /**
     * @param CarrierInterface $carrier
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function notifyLimitReachedForCarrier(CarrierInterface $carrier): void
    {
        if (
            !$carrier->getIsCapAlertDispatch() &&
            $this->notificationSender->sendCapByCarrierNotification($carrier)
        ) {
            $carrier->setIsCapAlertDispatch(true);
            $this->entitySaveHelper->persistAndSave($carrier);
        }
    }

    public function notifyLimitReachedByAffiliate(ConstraintByAffiliate $constraintByAffiliate, CarrierInterface $carrier): void
    {

        if (
            !$constraintByAffiliate->getIsCapAlertDispatch() &&
            $this->notificationSender->sendCapByAffiliateNotification($constraintByAffiliate, $carrier)
        ) {
            $constraintByAffiliate->setIsCapAlertDispatch(true);
            $this->entitySaveHelper->persistAndSave($constraintByAffiliate);
        }
    }
}