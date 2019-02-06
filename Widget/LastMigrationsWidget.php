<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Widget;

use Akeneo\Component\Batch\Job\BatchStatus;
use Akeneo\Component\Batch\Model\JobExecution;
use Doctrine\DBAL\Migrations\Finder\GlobFinder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping;
use Pim\Bundle\DashboardBundle\Widget\WidgetInterface;
use Pim\Bundle\EnrichBundle\Doctrine\ORM\Repository\JobExecutionRepository;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Widget to display / start last migrations availables on project
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle\Widget
 */
class LastMigrationsWidget implements WidgetInterface
{
    /**
     * Limit migrations display on dashboard
     *
     * @var int
     */
    const MIGRATIONS_LIMIT = 5;

    /**
     * Waiting status
     *
     * @var string
     */
    const STATUS_WAITING = 'grey';

    /**
     * Success status
     *
     * @var string
     */
    const STATUS_SUCCESS = 'success';

    /**
     * Failed status
     *
     * @var string
     */
    const STATUS_FAILED = 'important';

    /**
     * Migrations directory
     *
     * @var string
     */
    protected $migrationsDir;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var JobExecutionRepository
     */
    protected $jobExecutionRepository;

    /**
     * LastMigrationsWidget constructor.
     *
     * @param string                 $migrationDir
     * @param EntityManager          $entityManager
     * @param TranslatorInterface    $translator
     * @param JobExecutionRepository $jobExecutionRepository
     */
    public function __construct($migrationsDir, EntityManager $entityManager, TranslatorInterface $translator, JobExecutionRepository $jobExecutionRepository)
    {
        $this->migrationsDir          = $migrationsDir;
        $this->entityManager          = $entityManager;
        $this->translator             = $translator;
        $this->jobExecutionRepository = $jobExecutionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'last_migrations';
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplate()
    {
        return 'ClickAndMortarAkeneoMigrationsManagerBundle:Widget:last_migrations.html.twig';
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        $migrations = [];
        $statuses   = $this->getStatuses();

        // Get loaded migrations from database
        $connection       = $this->entityManager->getConnection();
        $loadedMigrations = $connection->executeQuery('SELECT version FROM migration_versions')->fetchAll();

        // Get migrations from directory
        $globFinder    = new GlobFinder();
        $rawMigrations = $globFinder->findMigrations($this->migrationsDir);
        $limitIndex    = 0;
        foreach ($rawMigrations as $rawMigrationName => $rawMigration) {
            if ($limitIndex >= self::MIGRATIONS_LIMIT) {
                break;
            }

            // Get execution id if possible
            $executionId = null;
            $execution   = $this->getLastExecutionByMigrationVersion($rawMigrationName);
            if ($execution !== null) {
                $executionId = $execution->getId();
            }

            // Check migration status
            $status = $statuses[self::STATUS_WAITING];
            foreach ($loadedMigrations as $loadedMigration) {
                if ($rawMigrationName === $loadedMigration['version']) {
                    if ($execution === null || $execution->getStatus() == new BatchStatus(BatchStatus::COMPLETED)) {
                        $status = $statuses[self::STATUS_SUCCESS];
                    } else {
                        $status = $statuses[self::STATUS_FAILED];
                    }
                    break;
                }
            }

            $migrations[] = [
                'name'         => $rawMigrationName,
                'class'        => $rawMigration,
                'status'       => $status,
                'execution_id' => $executionId,
            ];
            $limitIndex++;
        }

        return array_slice($migrations, 0, self::MIGRATIONS_LIMIT);
    }

    /**
     * Get statuses array
     *
     * @return array
     */
    protected function getStatuses()
    {
        return [
            self::STATUS_WAITING => [
                'value' => self::STATUS_WAITING,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.waiting'),
            ],
            self::STATUS_SUCCESS => [
                'value' => self::STATUS_SUCCESS,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.executed'),
            ],
            self::STATUS_FAILED => [
                'value' => self::STATUS_FAILED,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.error'),
            ],
        ];
    }

    /**
     * Get last job execution by $migrationVersion
     *
     * @param string $migrationVersion
     *
     * @return JobExecution
     */
    protected function getLastExecutionByMigrationVersion($migrationVersion)
    {
        /** @var JobExecution[] $executions */
        $executions = $this->jobExecutionRepository->createQueryBuilder('e')
                                                   ->innerJoin('e.jobInstance', 'j')
                                                   ->where('j.type = :type')
                                                   ->orderBy('e.endTime', 'DESC')
                                                   ->setParameters([
                                                       'type' => 'migration',
                                                   ])
                                                   ->getQuery()
                                                   ->getResult();

        // Check for version in execution parameters
        foreach ($executions as $execution) {
            $executionParameters = $execution->getRawParameters();
            if (
                isset($executionParameters['migrationVersion'])
                && $executionParameters['migrationVersion'] == $migrationVersion
            ) {
                return $execution;
            }
        }

        return null;
    }
}
