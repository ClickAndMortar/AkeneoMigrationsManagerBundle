<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Widget;

use Doctrine\DBAL\Migrations\Finder\GlobFinder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMapping;
use Pim\Bundle\DashboardBundle\Widget\WidgetInterface;
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
     * LastMigrationsWidget constructor.
     *
     * @param string        $migrationDir
     * @param EntityManager $entityManager
     */
    public function __construct($migrationsDir, EntityManager $entityManager, TranslatorInterface $translator)
    {
        $this->migrationsDir = $migrationsDir;
        $this->entityManager = $entityManager;
        $this->translator    = $translator;
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

            // Check migration status
            $status = $statuses[self::STATUS_WAITING];
            foreach ($loadedMigrations as $loadedMigration) {
                if ($rawMigrationName === $loadedMigration['version']) {
                    $status = $statuses[self::STATUS_SUCCESS];
                    break;
                }
            }

            $migrations[] = [
                'name'   => $rawMigrationName,
                'class'  => $rawMigration,
                'status' => $status,
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
        ];
    }
}
