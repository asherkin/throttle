<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190107164838 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->addColumn('lastview', 'datetime', array('notnull' => false));

        $crash->addIndex(array('lastview'));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('lastview');
    }
}
