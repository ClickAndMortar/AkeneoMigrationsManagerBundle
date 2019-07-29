<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Helper;

use Akeneo\Tool\Bundle\BatchBundle\Job\JobInstanceRepository;
use Akeneo\Tool\Component\Console\CommandLauncher;
use Akeneo\Tool\Component\Console\CommandResult;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Job helper
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle\Helper
 */
class JobHelper
{
    /**
     * Process timeout (2 hours)
     *
     * @var int
     */
    const PROCESS_TIMEOUT = 7200;

    /**
     * @var JobInstanceRepository
     */
    protected $jobInstanceRepository;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * JobHelper constructor.
     *
     * @param JobInstanceRepository $jobInstanceRepository
     * @param EntityManager         $entityManager
     * @param KernelInterface       $kernel
     */
    public function __construct(JobInstanceRepository $jobInstanceRepository, EntityManager $entityManager, KernelInterface $kernel)
    {
        $this->jobInstanceRepository = $jobInstanceRepository;
        $this->entityManager         = $entityManager;
        $this->kernel                = $kernel;
    }

    /**
     * Get parameters for given job $code
     *
     * @param string $code
     *
     * @return array
     */
    public function getParametersByJob(string $code)
    {
        /** @var JobInstance $jobInstance */
        $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($code);
        if ($jobInstance === null) {
            return [];
        }

        return $jobInstance->getRawParameters();
    }

    /**
     * Set parameters on given job $code
     *
     * @param string $code
     * @param array  $parameters
     *
     * @return bool
     */
    public function setParametersByJob(string $code, array $parameters)
    {
        /** @var JobInstance $jobInstance */
        $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($code);
        if ($jobInstance === null) {
            return false;
        }

        $jobInstance->setRawParameters($parameters);
        $this->entityManager->persist($jobInstance);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get mapping for given job $code (mapping is a custom job parameter added by AdvancedCsvConnectorBundle)
     *
     * @param string $code
     *
     * @return array
     *
     * @see https://github.com/ClickAndMortar/AdvancedCsvConnectorBundle
     */
    public function getMappingByJob(string $code)
    {
        $parameters = $this->getParametersByJob($code);
        if (empty($parameters) || !array_key_exists('mapping', $parameters)) {
            return [];
        }

        return json_decode($parameters['mapping'], true);
    }

    /**
     * Set mapping for given job $code (mapping is a custom job parameter added by AdvancedCsvConnectorBundle)
     *
     * @param string $code
     * @param array  $mapping
     *
     * @return bool
     *
     * @see https://github.com/ClickAndMortar/AdvancedCsvConnectorBundle
     */
    public function setMappingByJob(string $code, array $mapping)
    {
        $parameters = $this->getParametersByJob($code);
        if (empty($parameters) || !array_key_exists('mapping', $parameters)) {
            return false;
        }

        $parameters['mapping'] = json_encode($mapping, JSON_PRETTY_PRINT);
        $this->setParametersByJob($code, $parameters);

        return true;
    }

    /**
     * Get fixture complete path
     *
     * @param string $shortPath
     *
     * @return string
     */
    public function getFixtureCompletePath($shortPath)
    {
        return sprintf(
            '%s/fixtures/%s',
            $this->kernel->getRootDir(),
            $shortPath
        );
    }

    /**
     * Start a job instance by $code
     *
     * @param string $code
     * @param array  $parameters
     * @param string $username
     *
     * @return bool
     */
    public function startJobByCode(string $code, array $parameters = [], string $username = 'admin')
    {
        $pathFinder = new PhpExecutableFinder();
        $command    = sprintf(
            '%s %s/../bin/console akeneo:batch:job %s --username=%s',
            $pathFinder->find(),
            $this->kernel->getRootDir(),
            $code,
            $username
        );

        // Add parameters if necessary
        if (!empty($parameters)) {
            $command .= sprintf(' --config=\'%s\'', json_encode($parameters, JSON_UNESCAPED_SLASHES));
        }

        try {
            $process = new Process($command);
            $process->setTimeout(self::PROCESS_TIMEOUT);
            $process->setIdleTimeout(self::PROCESS_TIMEOUT);
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            return false;
        }

        return true;
    }
}
