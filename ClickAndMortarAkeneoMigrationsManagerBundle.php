<?php

namespace ClickAndMortar\AkeneoMigrationsManagerBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Migrations manager bundle by Click And Mortar
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package ClickAndMortar\AkeneoMigrationsManagerBundle
 */
class ClickAndMortarAkeneoMigrationsManagerBundle extends Bundle
{
    /**
     * Build.
     *
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}