<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20160126125206 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->addColumn('stackhash', 'string', array('length' => 80, 'notnull' => false));

        $crash->addIndex(array('stackhash'));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('stackhash');
    }
}
