<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130902151422 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->addIndex(array('processed', 'present'));
    }

    public function down(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->dropIndex('IDX_C24262827FB1B8BFDBCAE17');
    }
}
