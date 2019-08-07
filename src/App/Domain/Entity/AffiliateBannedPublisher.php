<?php


namespace App\Domain\Entity;


class AffiliateBannedPublisher
{
    /** @var string */
    private $uuid;

    /** @var Affiliate */
    private $affiliate;

    /** @var string */
    private $publisherId;

    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param Affiliate $affiliate
     */
    public function setAffiliate(Affiliate $affiliate): void
    {
        $this->affiliate = $affiliate;
    }

    /**
     * @return Affiliate
     */
    public function getAffiliate(): Affiliate
    {
        return $this->affiliate;
    }

    /**
     * @return string
     */
    public function getPublisherId(): ?string
    {
        return $this->publisherId;
    }

    /**
     * @param string $publisherId
     */
    public function setPublisherId(string $publisherId): void
    {
        $this->publisherId = $publisherId;
    }
}