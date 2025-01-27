<?php

namespace App\Command;

use DataDog\AuditBundle\Entity\AuditLog;
use DateTime;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class AuditClearOldLogEntriesCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'app:audit:clear-old-log-entries';


    /** @var EntityManagerInterface */
    private $entityManager;

    /**
     * AdminClearOldLogEntriesCommand constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {

        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Command will clear log entries older than a month');
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     * @throws ConnectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $queryBuilder = $this->entityManager->createQueryBuilder();

            $queryBuilder->delete(AuditLog::class, 'a')
                ->where("a.loggedAt < :month_ago")
                ->setParameters(['month_ago' => new DateTime('- 1 month')])
                ->getQuery()
                ->execute();

            $this->entityManager->getConnection()->commit();

            $io->success('Successfully deleted log entries older than a month');

        } catch (Throwable $e) {
            $this->entityManager->getConnection()->rollBack();

            $io->error($e->getMessage());
        }
    }
}
