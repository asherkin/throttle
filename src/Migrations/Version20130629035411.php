<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130629035411 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->addColumn('failed', 'boolean', array('default' => false));
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->dropColumn('failed');
    }
}
