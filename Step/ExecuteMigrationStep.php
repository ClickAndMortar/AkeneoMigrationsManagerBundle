<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Step;

use Akeneo\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Component\Batch\Step\AbstractStep;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\Batch\Job\BatchStatus;
use Akeneo\Component\Batch\Job\ExitStatus;
use Akeneo\Component\Batch\Item\DataInvalidItem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Execute migration and print details
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle\Step
 */
class ExecuteMigrationStep extends AbstractStep
{
    /**
     * Migration version parameter
     *
     * @var string
     */
    const PARAMETER_MIGRATION_VERSION = 'migrationVersion';

    /**
     * Migration timeout: 1 hour
     *
     * @var int
     */
    const MIGRATION_TIMEOUT = 3600;

    /**
     * Step execution
     *
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * ExecuteMigrationStep constructor.
     *
     * @param                          $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param JobRepositoryInterface   $jobRepository
     * @param KernelInterface          $kernel
     */
    public function __construct($name, EventDispatcherInterface $eventDispatcher, JobRepositoryInterface $jobRepository, KernelInterface $kernel)
    {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;

        // Check for migration version
        $jobParameters    = $this->stepExecution->getJobParameters();
        $migrationVersion = $jobParameters->get(self::PARAMETER_MIGRATION_VERSION);
        if ($migrationVersion === null) {
            $this->stepExecution->addWarning('batch_jobs.execute_migration.execute.errors.no_migration_version', [], new DataInvalidItem([]));
            $this->stepExecution->setStatus(new BatchStatus(BatchStatus::FAILED));
            $this->stepExecution->setExitStatus(new ExitStatus(ExitStatus::FAILED));
            $this->stepExecution->setEndTime(new \DateTime('now'));
        }

        // Execute migration
        $pathFinder              = new PhpExecutableFinder();
        $command                 = sprintf(
            '%s %s/../bin/console doctrine:migrations:execute -q %s',
            $pathFinder->find(),
            $this->kernel->getRootDir(),
            $migrationVersion
        );
        $executeMigrationProcess = new Process($command);
        $executeMigrationProcess->setTimeout(self::MIGRATION_TIMEOUT);
        $executeMigrationProcess->setIdleTimeout(self::MIGRATION_TIMEOUT);
        try {
            $executeMigrationProcess->mustRun(function ($type, $buffer) {
                $this->stepExecution->addWarning($buffer, [], new DataInvalidItem([]));
            });
        } catch (ProcessFailedException $exception) {
            $this->stepExecution->setStatus(new BatchStatus(BatchStatus::FAILED));
            $this->stepExecution->setExitStatus(new ExitStatus(ExitStatus::FAILED));
            $this->stepExecution->setEndTime(new \DateTime('now'));
            $this->stepExecution->addFailureException($exception);
        }
    }
}
