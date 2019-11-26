<?php

namespace SubscriptionBundle\Repository;

use CommonDataBundle\Entity\Interfaces\CarrierInterface;
use DateTime;
use Doctrine\ORM\EntityRepository;
use IdentificationBundle\Entity\User;
use SubscriptionBundle\Entity\Subscription;
use SubscriptionBundle\Entity\SubscriptionPack;

/**
 * SubscriptionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SubscriptionRepository extends EntityRepository
{
    /**
     * @param User $user
     * @return Subscription|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findCurrentSubscriptionByOwner(User $user): ?Subscription
    {

        // Retrieving the latest subscription created by this user
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.user = :user')
            ->addOrderBy('s.created', 'DESC')
            ->setMaxResults(1)
            ->setParameter('user', $user);


        $existingActiveSubscription = $qb->getQuery()->getOneOrNullResult();
        return $existingActiveSubscription;
    }

    /**
     * @param User         $user
     * @param Subscription $subscription
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findPreviousUnSubscribedSubscription(User $user, Subscription $subscription): ?Subscription
    {

        // Retrieving the latest subscription created by this user
        $qb = $this->createQueryBuilder('s');
        $qb->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->andWhere('s.id != :id')
            ->addOrderBy('s.created', 'DESC')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->setParameter('id', $subscription->getUuid())
            ->setParameter('status', Subscription::IS_INACTIVE);


        $existingActiveSubscription = $qb->getQuery()->getOneOrNullResult();
        return $existingActiveSubscription;
    }


    /**
     * @param CarrierInterface $carrier
     * @return Subscription[]
     * @throws \Exception
     */
    public function getExpiredSubscriptions(CarrierInterface $carrier)
    {
        // $startedLimit = new DateTime('-' . $carrier->getTrialPeriod() . ' days');

        $qb    = $this->getEntityManager()->createQueryBuilder();
        $query = $qb->select('s')
            ->from($this->getEntityName(), 's')
            ->join('s.user', 'user')
            ->join('user.carrier', 'carrier')
            ->andWhere('s.currentStage = :subAction')
            ->andWhere('s.status = :subStatus')
            ->andWhere('s.renewDate < :currentTime')
            ->andWhere('(carrier = :carrier )')
            ->setParameters([
                'subStatus'   => Subscription::IS_ACTIVE,
                'subAction'   => Subscription::ACTION_SUBSCRIBE,
                'currentTime' => new DateTime(),
                /*'startedLimit' => $startedLimit,*/
                'carrier'     => $carrier
            ])
            ->setMaxResults(100)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * get last AI index
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLastId()
    {
        $qb    = $this->getEntityManager()->createQueryBuilder();
        $query = $qb->select('s.id')
            ->from($this->getEntityName(), 's')
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        return $query->getSingleScalarResult();
    }

    /**
     * @param CarrierInterface $carrier
     * @param SubscriptionPack $subscriptionPack
     *
     * @return Subscription[]
     * @throws \Exception
     */
    public function findExpiringTomorrowSubscriptions(CarrierInterface $carrier, SubscriptionPack $subscriptionPack)
    {
        $startedLimit = new DateTime('-' . $subscriptionPack->getFinalPeriodForSubscription() . ' days');

        $query = $this->createQueryBuilder('s')
            ->join('s.user', 'user')
            ->join('user.carrier', 'carrier')
            ->andWhere('s.currentStage = :subAction')
            ->andWhere('s.status = :subStatus')
            ->andWhere("DATE(s.renewDate) = DATE_ADD(CURRENT_DATE(), 1, 'DAY')")
            ->andWhere('(s.lastRenewAlertDate < :startedLimit) OR s.lastRenewAlertDate IS NULL')
            ->andWhere('(carrier = :carrier )')
            ->setParameters([
                'subStatus'    => Subscription::IS_ACTIVE,
                'subAction'    => Subscription::ACTION_SUBSCRIBE,
                'carrier'      => $carrier,
                'startedLimit' => $startedLimit
            ])
            ->setMaxResults(100)
            ->getQuery();

        return $query->getResult();
    }

}


