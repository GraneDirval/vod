<?php


namespace App\Domain\Service\OneClickFlow;


use App\Domain\Entity\Affiliate;
use App\Domain\Entity\Campaign;
use App\Domain\Entity\Carrier;

class OneClickFlowResolver
{
    /**
     * @var OneClickFlowScheduler
     */
    private $oneClickFlowScheduler;
    /**
     * @var OneClickFlowCarriersProvider
     */
    private $oneClickFlowCarriersProvider;

    public function __construct(
        OneClickFlowScheduler $oneClickFlowScheduler,
        OneClickFlowCarriersProvider $oneClickFlowCarriersProvider
    )
    {
        $this->oneClickFlowScheduler        = $oneClickFlowScheduler;
        $this->oneClickFlowCarriersProvider = $oneClickFlowCarriersProvider;
    }

    /**
     * @param Carrier       $carrier
     * @param Campaign|null $campaign
     *
     * @return bool
     */
    public function isLandingDisabled(Carrier $carrier, Campaign $campaign = null): bool
    {
        try {
            if ($carrier->isOneClickFlow()) {
                return true;
            }

            if (!$campaign) {
                return false;
            }

            $schedule                            = $this->oneClickFlowScheduler->getScheduleAsArray($campaign->getSchedule());
            $isCampaignScheduleExistAndTriggered = $schedule
                ? $this->oneClickFlowScheduler->isNowInCampaignSchedule($schedule)
                : true;

            $isLPOffByCampaign = $campaign->isOneClickFlow() && $isCampaignScheduleExistAndTriggered;

            return $isLPOffByCampaign;

        } catch (\Throwable $e) {
            return false;
        }
    }
}