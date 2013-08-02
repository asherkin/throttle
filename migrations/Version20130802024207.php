<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130802024207 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $server = $schema->createTable('server');

        $server->addColumn('owner', 'bigint', array('unsigned' => true));
        $server->addColumn('id', 'string', array('length' => 64, 'default' => ''));

        $server->setPrimaryKey(array('owner', 'id'));

        $user = $schema->getTable('user');
        $server->addForeignKeyConstraint($user, array('owner'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));

        $crash = $schema->getTable('crash');
        $crash->addColumn('server', 'string', array('length' => 64, 'notnull' => false));
    }

    public function down(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('server');

        $schema->dropTable('server');
    }
}
