<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130901024450 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->addIndex(['name', 'identifier']);
    }

    public function down(Schema $schema): void
    {
        $module = $schema->getTable('module');
        $module->dropIndex('IDX_C2426285E237E06772E836A');
    }
}
