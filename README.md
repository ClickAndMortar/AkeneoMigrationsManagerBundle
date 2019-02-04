# Akeneo Migrations Manager Bundle - Click And Mortar

This bundle allows to manage migrations on dashboard on your Akeneo project.

Made by :heart: by C&M

## Installation

Add package with composer:
```bash
composer require clickandmortar/akeneo-migrations-manager-bundle "^1.0"
```

Add bundle in your **`app/AppKernel.php`** file:
```php
$bundles = array(
            ...
            new ClickAndMortar\AkeneoMigrationsManagerBundle\ClickAndMortarAkeneoMigrationsManagerBundle(),
        );
```
