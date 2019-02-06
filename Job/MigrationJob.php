<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Job;

use Akeneo\Component\Batch\Job\Job;
use Akeneo\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Component\Batch\Model\JobExecution;
use Akeneo\Component\Batch\Model\StepExecution;
use ClickAndMortar\AkeneoMigrationsManagerBundle\Step\MigrationStep;
use Akeneo\Component\Batch\Job\BatchStatus;
use Akeneo\Component\Batch\Event\EventInterface;
use Akeneo\Component\Batch\Event\JobExecutionEvent;
use Symfony\Component\EventDispatcher\Event;
use Akeneo\Component\Batch\Item\DataInvalidItem;
use Akeneo\Component\Batch\Job\ExitStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Job with dynamic steps creation from migration execution
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle
 */
class MigrationJob extends Job
{
    /**
     * Migration version parameter
     *
     * @var string
     */
    const PARAMETER_MIGRATION_VERSION = 'migrationVersion';

    /**
     * New step prefix from process data
     *
     * @var string
     */
    const PREFIX_NEW_STEP = 'new_step:';

    /**
     * Migration timeout: 1 hour
     *
     * @var int
     */
    const MIGRATION_TIMEOUT = 3600;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var JobExecution
     */
    protected $jobExecution;

    /**
     * @var StepExecution
     */
    protected $currentStepExecution;

    /**
     * @var string
     */
    protected $migrationVersion;

    /**
     * @var Process
     */
    protected $executeMigrationProcess;

    /**
     * MigrationJob constructor.
     *
     * @param                          $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param JobRepositoryInterface   $jobRepository
     * @param array                    $steps
     * @param KernelInterface          $kernel
     */
    public function __construct($name, EventDispatcherInterface $eventDispatcher, JobRepositoryInterface $jobRepository, $steps = [], KernelInterface $kernel)
    {
        parent::__construct($name, $eventDispatcher, $jobRepository, $steps);
        $this->kernel = $kernel;
    }

    /**
     * Execute migration and generate steps from migration messages
     *
     * @param JobExecution $jobExecution
     */
    protected function doExecute(JobExecution $jobExecution)
    {
        $this->jobExecution = $jobExecution;
        if ($this->handleCheckMigrationVersionStep()) {
            $this->handleMigrationSteps();
        }

        // Update the job status to be the same as the last step
        if ($this->currentStepExecution !== null) {
            $this->dispatchJobExecutionEvent(EventInterface::BEFORE_JOB_STATUS_UPGRADE, $jobExecution);

            $jobExecution->upgradeStatus($this->currentStepExecution->getStatus()->getValue());
            $jobExecution->setExitStatus($this->currentStepExecution->getExitStatus());
            $this->jobRepository->updateJobExecution($jobExecution);
        }
    }

    /**
     * Create a new dynamic step to check migration version from job parameters
     *
     * @return bool
     */
    protected function handleCheckMigrationVersionStep()
    {
        $step                       = new MigrationStep('check_migration_version', $this->eventDispatcher, $this->jobRepository);
        $this->currentStepExecution = $this->handleStep($step, $this->jobExecution);

        // Check for migration version
        $this->migrationVersion = $this->getMigrationVersionFromJobParameters();
        if ($this->migrationVersion === null) {
            $this->currentStepExecution->addWarning('batch_jobs.execute_migration.check_migration_version.errors.no_migration_version', [], new DataInvalidItem([]));
            $this->currentStepExecution->setStatus(new BatchStatus(BatchStatus::FAILED));
            $this->currentStepExecution->setExitStatus(new ExitStatus(ExitStatus::FAILED));
            $this->currentStepExecution->setEndTime(new \DateTime('now'));
        }
        $this->jobRepository->updateStepExecution($this->currentStepExecution);

        if ($this->currentStepExecution->getStatus()->getValue() !== BatchStatus::COMPLETED) {
            return false;
        }

        return true;
    }

    /**
     * Execute migration and generate steps dynamically
     *
     * @return void
     */
    protected function handleMigrationSteps()
    {
        // Execute migration
        $pathFinder                    = new PhpExecutableFinder();
        $command                       = sprintf(
            '%s %s/../bin/console doctrine:migrations:execute -q %s',
            $pathFinder->find(),
            $this->kernel->getRootDir(),
            $this->migrationVersion
        );
        $this->executeMigrationProcess = new Process($command);
        $this->executeMigrationProcess->setTimeout(self::MIGRATION_TIMEOUT);
        $this->executeMigrationProcess->setIdleTimeout(self::MIGRATION_TIMEOUT);

        try {
            $this->executeMigrationProcess->mustRun(function ($type, $data) {
                if ($type !== Process::ERR) {
                    $results = explode("\n", $data);
                    foreach ($results as $result) {
                        // Do not process empty returns
                        if (empty($result)) {
                            continue;
                        }

                        // Create new step or warning message
                        if ($this->isNewStepData($result)) {
                            $result                     = $this->getStepData($result);
                            $step                       = new MigrationStep($result, $this->eventDispatcher, $this->jobRepository);
                            $this->currentStepExecution = $this->handleStep($step, $this->jobExecution);
                            $this->jobRepository->updateStepExecution($this->currentStepExecution);

                            if ($this->currentStepExecution->getStatus()->getValue() !== BatchStatus::COMPLETED) {
                                throw new ProcessFailedException($this->executeMigrationProcess);
                            }
                        } else {
                            $this->currentStepExecution->addWarning($result, [], new DataInvalidItem([]));
                        }
                    }
                }
            });
        } catch (ProcessFailedException $exception) {
            $this->currentStepExecution->setStatus(new BatchStatus(BatchStatus::FAILED));
            $this->currentStepExecution->setExitStatus(new ExitStatus(ExitStatus::FAILED));
            $this->currentStepExecution->setEndTime(new \DateTime('now'));
            $this->currentStepExecution->addFailureException($exception);
        }
    }

    /**
     * Get migration version
     *
     * @return string
     */
    protected function getMigrationVersionFromJobParameters()
    {
        $jobParameters    = $this->jobExecution->getJobParameters();
        $migrationVersion = $jobParameters->get(self::PARAMETER_MIGRATION_VERSION);

        return $migrationVersion;
    }

    /**
     * Check if $data from process is new step or not
     *
     * @param string $data
     *
     * @return bool
     */
    protected function isNewStepData($data)
    {
        return strpos($data, self::PREFIX_NEW_STEP) !== false;
    }

    /**
     * Remove step prefix from process $data
     *
     * @param string $data
     *
     * @return string
     */
    protected function getStepData($data)
    {
        return str_replace(self::PREFIX_NEW_STEP, '', $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatchJobExecutionEvent($eventName, JobExecution $jobExecution)
    {
        $event = new JobExecutionEvent($jobExecution);
        $this->dispatch($eventName, $event);
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatch($eventName, Event $event)
    {
        $this->eventDispatcher->dispatch($eventName, $event);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultExitStatusForFailure(\Exception $e)
    {
        if ($e instanceof JobInterruptedException || $e->getPrevious() instanceof JobInterruptedException) {
            $exitStatus = new ExitStatus(ExitStatus::STOPPED);
            $exitStatus->addExitDescription(get_class(new JobInterruptedException()));
        } else {
            $exitStatus = new ExitStatus(ExitStatus::FAILED);
            $exitStatus->addExitDescription($e);
        }

        return $exitStatus;
    }

    /**
     * {@inheritdoc}
     */
    protected function updateStatus(JobExecution $jobExecution, $status)
    {
        $jobExecution->setStatus(new BatchStatus($status));
    }

    /**
     * {@inheritdoc}
     */
    protected function createWorkingDirectory()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('akeneo_batch_') . DIRECTORY_SEPARATOR;
        try {
            $this->filesystem->mkdir($path);
        } catch (IOException $e) {
            // this exception will be catched by {Job->execute()} and will set the batch as failed
            throw new RuntimeErrorException('Failed to write to file %path%', ['%path%' => $path]);
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteWorkingDirectory($directory)
    {
        if ($this->filesystem->exists($directory)) {
            $this->filesystem->remove($directory);
        }
    }
}
