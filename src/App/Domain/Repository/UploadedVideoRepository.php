<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Subcategory;
use App\Domain\Entity\UploadedVideo;

/**
 * UploadedVideoRepository
 */
class UploadedVideoRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param int $offset
     * @param int $count
     *
     * @return array
     *
     * @throws \Exception
     */
    public function findNotExpiredWithCategories(): array
    {
        $queryBuilder = $this->createQueryBuilder('v');
        $q = $queryBuilder
            ->leftJoin('v.subcategory', 'subcategory')
            ->leftJoin('subcategory.parent', 'category')
            ->where($queryBuilder->expr()->orX('v.expiredDate > :currentDateTime', 'v.expiredDate IS NULL'))
            ->andWhere('v.status = :status')
            ->orderBy('v.createdDate', 'DESC')
            ->setParameter('currentDateTime', new \DateTime())
            ->setParameter('status', UploadedVideo::STATUS_READY)
            ->addSelect('subcategory', 'category');

        return $q->getQuery()->getResult();
    }

    /**
     * @param Subcategory[] $subcategories
     *
     * @param int $offset
     * @param int $count
     * @return array
     *
     * @throws \Exception
     */
    public function findNotExpiredBySubcategories(array $subcategories, int $offset = 0, int $count = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('v');
        $query = $queryBuilder
            ->where($queryBuilder->expr()->orX('v.expiredDate > :currentDateTime', 'v.expiredDate IS NULL'))
            ->andWhere('v.subcategory = :subcategory')
            ->andWhere('v.status = :status')
            ->orderBy('v.createdDate', 'DESC')
            ->setParameters([
                'subcategory' => $subcategories,
                'currentDateTime' => new \DateTime(),
                'status' => UploadedVideo::STATUS_READY
            ])
            ->setMaxResults($count)
            ->setFirstResult($offset)
            ->getQuery();

        return $query->getResult();
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public function findExpiredVideo(): array
    {
        $queryBuilder = $this->createQueryBuilder('v');
        $query = $queryBuilder
            ->where(':currentDateTime > v.expiredDate')
            ->setParameter('currentDateTime', new \DateTime())
            ->getQuery();

        return $query->getResult();
    }
}