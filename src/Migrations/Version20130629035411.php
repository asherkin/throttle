<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130629035411 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->addColumn('failed', 'boolean', ['default' => false]);
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->dropColumn('failed');
    }
}
