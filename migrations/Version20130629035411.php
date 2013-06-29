<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130629035411 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');

        $crash->addColumn('failed', 'boolean', array('default' => false));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');

        $crash->dropColumn('failed');
    }
}
