<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130902133132 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->addColumn('processed', 'boolean', array('default' => false));
        $module->addColumn('present', 'boolean', array('default' => false));
    }

    public function down(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->dropColumn('processed');
        $module->dropColumn('present');
    }
}
