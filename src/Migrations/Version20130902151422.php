<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130902151422 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->addIndex(array('processed', 'present'));
    }

    public function down(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->dropIndex('IDX_C24262827FB1B8BFDBCAE17');
    }
}
