<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\Helper;

use Akeneo\Bundle\BatchBundle\Job\JobInstanceRepository;
use Akeneo\Component\Batch\Model\JobInstance;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\ApiBundle\Doctrine\ORM\Repository\AttributeRepository;
use Pim\Bundle\CatalogBundle\Entity\Attribute;
use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Pim\Bundle\CatalogBundle\Entity\Family;
use Psr\Container\ContainerInterface;

/**
 * Class EntityHelper
 *
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle\Helper
 * @author  Michael BOUVY <michael.bouvy@clickandmortar.fr>
 */
class EntityHelper
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $code
     * @param string $type
     * @param string $group
     * @param bool   $localizable
     * @param bool   $scopable
     * @param array  $parameters
     *
     * @return Attribute
     */
    public function createAttribute(string $code, string $type, string $group, bool $localizable = false, bool $scopable = false, array $parameters = [])
    {
        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = $this->container->get('pim_api.repository.attribute');

        /** @var Attribute $attribute */
        $attribute = $attributeRepository->findOneByIdentifier($code);

        $attributeData = [
            'code'        => $code,
            'type'        => $type,
            'localizable' => $localizable,
            'scopable'    => $scopable,
            'group'       => $group,
        ];
        $attributeData = array_merge($attributeData, $parameters);

        if (null === $attribute) {
            $attribute = $this->container->get('pim_catalog.factory.attribute')->create();
        }

        $this->container->get('pim_catalog.updater.attribute')->update($attribute, $attributeData);
        $this->container->get('pim_catalog.saver.attribute')->save($attribute);

        return $attribute;
    }

    /**
     * @param Attribute $attribute
     * @param string    $label
     * @param string    $locale
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function createTranslation(Attribute $attribute, string $label, string $locale)
    {
        $translation = $attribute->getTranslation($locale);
        $translation->setLabel($label);

        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->persist($translation);
        $entityManager->persist($attribute);
        $entityManager->flush();
    }

    /**
     * @param Attribute $attribute
     * @param array     $options
     */
    public function createOptions($attribute, array $options = [])
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        foreach ($options as $code) {
            $attributeOption = null;
            foreach ($attribute->getOptions() as $attrOption) {
                /** @var AttributeOption $attrOption */
                if ($attrOption->getCode() === $code) {
                    $attributeOption = $attrOption;
                    break;
                }
            }

            if ($attributeOption === null) {
                $attributeOption = new AttributeOption();
            }

            $attributeOption->setAttribute($attribute);
            $attributeOption->setCode($code);
            $attribute->addOption($attributeOption);

            $entityManager->persist($attributeOption);
        }
        $entityManager->persist($attribute);
        $entityManager->flush();
    }

    /**
     * @param Attribute $attribute
     * @param Family    $family
     */
    public function addAttributeToFamily(Attribute $attribute, Family $family)
    {
        $family->addAttribute($attribute);
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->persist($family);
        $entityManager->flush();
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
        /** @var JobInstanceRepository $jobInstanceRepository */
        $jobInstanceRepository = $this->container->get('akeneo_batch.job.job_instance_repository');
        /** @var JobInstance $jobInstance */
        $jobInstance = $jobInstanceRepository->findOneByIdentifier($code);
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
        /** @var JobInstanceRepository $jobInstanceRepository */
        $jobInstanceRepository = $this->container->get('akeneo_batch.job.job_instance_repository');
        /** @var JobInstance $jobInstance */
        $jobInstance = $jobInstanceRepository->findOneByIdentifier($code);
        if ($jobInstance === null) {
            return false;
        }

        $jobInstance->setRawParameters($parameters);
        /** @var EntityManager $entityManager */
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $entityManager->persist($jobInstance);
        $entityManager->flush();

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
}
