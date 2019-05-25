<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130902133132 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->addColumn('processed', 'boolean', array('default' => false));
        $module->addColumn('present', 'boolean', array('default' => false));
    }

    public function down(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->dropColumn('processed');
        $module->dropColumn('present');
    }
}
