<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Step;

use Akeneo\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Component\Batch\Step\AbstractStep;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\Batch\Job\BatchStatus;
use Akeneo\Component\Batch\Job\ExitStatus;
use Akeneo\Component\Batch\Item\DataInvalidItem;
use Pim\Bundle\VersioningBundle\Manager\VersionManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Default migration step used for dynamic generation in Migration Job
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle\Step
 */
class MigrationStep extends AbstractStep
{
    /**
     * {@inheritdoc}
     */
    protected function doExecute(StepExecution $stepExecution)
    {
        // Do nothing here
    }
}
