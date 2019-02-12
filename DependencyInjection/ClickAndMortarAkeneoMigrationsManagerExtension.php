<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class ClickAndMortarAkeneoMigrationsManagerExtension
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package Pim\Bundle\DashboardBundle\DependencyInjection
 */
class ClickAndMortarAkeneoMigrationsManagerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('widgets.yml');
        $loader->load('helpers.yml');
        $loader->load('jobs.yml');
        $loader->load('job_defaults.yml');
        $loader->load('job_constraints.yml');
        $loader->load('normalizers.yml');
    }
}
