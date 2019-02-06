<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Normalizer;

use Akeneo\Component\Batch\Model\StepExecution;
use Pim\Bundle\ImportExportBundle\Normalizer\StepExecutionNormalizer as BaseNormalizerInterface;

/**
 * Normalizer of StepExecution instance
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class StepExecutionNormalizer extends BaseNormalizerInterface
{
    /**
     * Custom execute migration job name
     *
     * @var string
     */
    const JOB_NAME_EXECUTE_MIGRATION = 'execute_migration';

    /**
     * {@inheritdoc}
     */
    public function normalize($stepExecution, $format = null, array $context = [])
    {
        /** @var StepExecution $stepExecution */
        $normalizedStepExecution = parent::normalize($stepExecution, $format, $context);

        // Avoid translation for MigrationStep
        if (strpos($normalizedStepExecution['label'], self::JOB_NAME_EXECUTE_MIGRATION) !== false) {
            $normalizedStepExecution['label'] = $stepExecution->getStepName();
        }

        return $normalizedStepExecution;
    }
}
