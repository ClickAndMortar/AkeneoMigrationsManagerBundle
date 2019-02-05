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

Create job used to manage migrations:

```
php bin/console akeneo:batch:create-job internal execute_migration migration execute_migration_by_version
```

## Usage

Create a new classic migration with command:

```
php bin/console doctrine:migrations:generate
```

Optional: You can rename your new migration to use release version and have better name in migrations widget: `Version1_0_1.php`

Start migration with created job to enable tracking in widget view:

```
php bin/console akeneo:batch:job -c '{"migrationVersion":"<my_version>"}' execute_migration_by_version
```



