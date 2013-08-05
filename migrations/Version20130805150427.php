<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130805150427 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->addIndex(array('timestamp'));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->dropIndex('IDX_D7E8F0DFA5D6E63E');
    }
}
