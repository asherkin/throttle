<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190215233724 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');

        $crash->addColumn('crashmodule', 'string', array('length' => 255, 'notnull' => false));
        $crash->addColumn('crashfunction', 'string', array('length' => 769, 'notnull' => false));

        $crash->addIndex(array('crashmodule', 'crashfunction'));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');

        $crash->dropColumn('crashmodule');
        $crash->dropColumn('crashfunction');
    }
}
