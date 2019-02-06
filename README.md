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
php bin/console akeneo:batch:create-job internal execute_migration migration execute_migration_by_version '{"migrationVersion":null}' 'Execute migration by version'
```

## Usage

* Create a new classic migration with command:

```
php bin/console doctrine:migrations:generate
```

* Create your migration by extending `AbstractStepMigration` to use steps methods. Example:

```
<?php

namespace Pim\Upgrade\Schema;

use ClickAndMortar\AkeneoMigrationsManagerBundle\Migration\AbstractStepMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Class Version20190121174114
 *
 * @author  Simon CARRE <simon.carre@clickandmortar.fr>
 * @package Pim\Upgrade\Schema
 */
class Version20190121174114 extends AbstractStepMigration
{
    /**
     * Migration label
     *
     * @var string
     */
    const MIGRATION_LABEL = 'Update to 1.0.1';

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->createNewStep('Start a new step');
        
        // Process here
        
        $this->addWarning('Error: Bad process');
        $this->createNewStep('Start the last step');
        
        // Process here
    }

    /**
     * Get migration label used in dashboard widget
     *
     * @return string
     */
    public static function getLabel()
    {
        return self::MIGRATION_LABEL;
    }
}

```

* Start migration with created job to enable tracking in widget view:

```
php bin/console akeneo:batch:job -c '{"migrationVersion":"<my_version>"}' execute_migration_by_version
```



