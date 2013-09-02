<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130901024450 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->addIndex(array('name', 'identifier'));
    }

    public function down(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->dropIndex('IDX_C2426285E237E06772E836A');
    }
}
