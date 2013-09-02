<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130902230947 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->addColumn('output', 'text', array('notnull' => false));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('output');
    }
}
