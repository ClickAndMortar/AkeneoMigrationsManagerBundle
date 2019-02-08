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
     * Limit executed migrations display on dashboard
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
     * In progress status
     *
     * @var string
     */
    const STATUS_IN_PROGRESS = 'action';

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
     * @var JobExecution[]
     */
    protected $loadedExecutions;

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
        $executedMigrations    = [];
        $notExecutedMigrations = [];
        $statuses              = $this->getStatuses();

        // Get migrations from directory
        $globFinder    = new GlobFinder();
        $rawMigrations = $globFinder->findMigrations($this->migrationsDir);
        $limitIndex    = 0;
        foreach ($rawMigrations as $rawMigrationName => $rawMigration) {
            // Get execution data if possible
            $executionData = [];
            $execution     = $this->getLastExecutionByMigrationVersion($rawMigrationName);
            if ($execution !== null) {
                $executionData = [
                    'id'          => $execution->getId(),
                    'create_time' => $execution->getCreateTime() !== null ? $execution->getCreateTime()
                                                                                      ->getTimestamp() : null,
                ];
            }

            // Get status from execution
            $status = $statuses[self::STATUS_WAITING];
            if ($execution !== null) {
                switch ($execution->getStatus()) {
                    case new BatchStatus(BatchStatus::COMPLETED):
                        $status = $statuses[self::STATUS_SUCCESS];
                        break;
                    case new BatchStatus(BatchStatus::FAILED):
                        $status = $statuses[self::STATUS_FAILED];
                        break;
                    default:
                        $status = $statuses[self::STATUS_IN_PROGRESS];
                        break;
                }
            }

            if (empty($executionData)) {
                $notExecutedMigrations[] = $this->getSerializedMigration($rawMigrationName, $status, $executionData);
            } else {
                $executedMigrations[] = $this->getSerializedMigration($rawMigrationName, $status, $executionData);
            }
            $limitIndex++;
        }

        // Sort executed migrations by creation time
        usort($executedMigrations, function ($a, $b) {
            return $b['execution']['create_time'] - $a['execution']['create_time'];
        });
        $migrations = array_merge($notExecutedMigrations, array_slice($executedMigrations, 0, self::MIGRATIONS_LIMIT));

        return $migrations;
    }

    /**
     * Get statuses array
     *
     * @return array
     */
    protected function getStatuses()
    {
        return [
            self::STATUS_WAITING     => [
                'value' => self::STATUS_WAITING,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.waiting'),
            ],
            self::STATUS_SUCCESS     => [
                'value' => self::STATUS_SUCCESS,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.executed'),
            ],
            self::STATUS_FAILED      => [
                'value' => self::STATUS_FAILED,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.error'),
            ],
            self::STATUS_IN_PROGRESS => [
                'value' => self::STATUS_IN_PROGRESS,
                'label' => $this->translator->trans('candm_migrations_manager.widget.last_migrations.in_progress'),
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
        if (empty($this->loadedExecutions)) {
            $this->loadedExecutions = $this->jobExecutionRepository->createQueryBuilder('e')
                                                                   ->innerJoin('e.jobInstance', 'j')
                                                                   ->where('j.type = :type')
                                                                   ->orderBy('e.createTime', 'DESC')
                                                                   ->setParameter('type', 'migration')
                                                                   ->getQuery()
                                                                   ->getResult();
        }

        // Check for version in execution parameters
        foreach ($this->loadedExecutions as $loadedExecution) {
            $executionParameters = $loadedExecution->getRawParameters();
            if (
                isset($executionParameters['migrationVersion'])
                && $executionParameters['migrationVersion'] == $migrationVersion
            ) {
                return $loadedExecution;
            }
        }

        return null;
    }

    /**
     * Get migration label by $version
     *
     * @param string $version
     *
     * @return string
     */
    protected function getMigrationLabelByVersion($version)
    {
        $migrationClassname = sprintf('Pim\Upgrade\Schema\Version%s', $version);
        if (
            class_exists($migrationClassname)
            && method_exists($migrationClassname, 'getLabel')
        ) {
            return $migrationClassname::getLabel();
        }

        return $version;
    }

    /**
     * Get serialized migration data
     *
     * @param string $rawMigrationName
     * @param array  $status
     * @param array  $executionData
     *
     * @return array
     */
    protected function getSerializedMigration($rawMigrationName, $status, $executionData)
    {
        return [
            'name'      => $this->getMigrationLabelByVersion($rawMigrationName),
            'code'      => $rawMigrationName,
            'status'    => $status,
            'execution' => $executionData,
        ];
    }
}
