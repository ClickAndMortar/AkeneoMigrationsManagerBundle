<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Migration;

use Doctrine\DBAL\Migrations\AbstractMigration;
use ClickAndMortar\AkeneoMigrationsManagerBundle\Job\MigrationJob;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract step migration
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle\Migration
 */
abstract class AbstractStepMigration extends AbstractMigration implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Create new step
     *
     * @param string $label
     *
     * @return void
     */
    public function createNewStep($label)
    {
        echo sprintf(
            "%s%s\n",
            MigrationJob::PREFIX_NEW_STEP,
            $label
        );
    }

    /**
     * Add warning message in current step execution
     *
     * @param string $message
     *
     * @return void
     */
    public function addWarning($message)
    {
        echo sprintf("%s\n", $message);
    }

    /**
     * @param Schema $schema
     *
     * @throws \Doctrine\DBAL\Migrations\IrreversibleMigrationException
     */
    public function down(Schema $schema)
    {
        $this->throwIrreversibleMigrationException();
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Get migration label used in dashboard widget
     *
     * @return string
     */
    abstract public static function getLabel();
}
