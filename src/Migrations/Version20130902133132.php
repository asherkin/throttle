<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130902133132 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->addColumn('processed', 'boolean', ['default' => false]);
        $module->addColumn('present', 'boolean', ['default' => false]);
    }

    public function down(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->dropColumn('processed');
        $module->dropColumn('present');
    }
}
