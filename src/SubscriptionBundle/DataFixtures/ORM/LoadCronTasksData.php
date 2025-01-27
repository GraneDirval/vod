<?php
/**
 * Created by PhpStorm.
 * User: dmitriy
 * Date: 30.04.18
 * Time: 16:09
 */

namespace SubscriptionBundle\DataFixtures\ORM;


use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use ExtrasBundle\Utils\UuidGenerator;
use SubscriptionBundle\Entity\CronTask;

class LoadCronTasksData extends AbstractFixture
{

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     *
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        $data = [
            ["1", "mobilinkPakistanMassRenewCronTask", "0"],
            ["2", "telenorPakistanDOTMassRenewCronTask", "0"],
            ["3", "hutchIndonesiaMassRenewCronTask", "0"],
            ["4", "zainKSAMassRenewCronTask", "0"],
            ["5", "zongPKMassRenewCronTask", "0"],
            ["6", "beelineKZMassRenewCronTask", "0"],
        ];


        foreach ($data as $row) {
            list($id, $name, $isRunning) = $row;

            $cronTask = new CronTask(UuidGenerator::generate());
            $cronTask->setCronName($name);
            $cronTask->setIsRunning($isRunning);

            $this->addReference(sprintf('cron_task_%s', $id), $cronTask);

            $manager->persist($cronTask);
        }

        $manager->flush();
    }

}