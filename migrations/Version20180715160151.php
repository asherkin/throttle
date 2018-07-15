<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20180715160151 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $share = $schema->createTable('share');

        $share->addColumn('owner', 'bigint', array('unsigned' => true));
        $share->addColumn('user', 'bigint', array('unsigned' => true));
        $share->addColumn('accepted', 'boolean', array('default' => false));

        $share->setPrimaryKey(array('owner', 'user'));

        $user = $schema->getTable('user');
        $share->addForeignKeyConstraint($user, array('owner'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
        $share->addForeignKeyConstraint($user, array('user'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
    }

    public function down(Schema $schema)
    {
        $schema->dropTable('share');
    }
}
