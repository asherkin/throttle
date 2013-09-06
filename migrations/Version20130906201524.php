<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130906201524 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $notice = $schema->getTable('notice');
 
        $notice->addColumn('rule', 'string', array('length' => 4095));
    }

    public function down(Schema $schema)
    {
        $notice = $schema->getTable('notice');

        $notice->dropColumn('rule');
    }
}
