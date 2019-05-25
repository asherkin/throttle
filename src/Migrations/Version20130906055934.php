<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130906055934 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $notice = $schema->createTable('notice');

        $notice->addColumn('id', 'string', array('length' => 255));
        $notice->addColumn('severity', 'string', array('length' => 255));
        $notice->addColumn('text', 'string', array('length' => 4095));

        $notice->setPrimaryKey(array('id'));

        $crashnotice = $schema->createTable('crashnotice');

        $crashnotice->addColumn('crash', 'string', array('length' => 12, 'fixed' => true));
        $crashnotice->addColumn('notice', 'string', array('length' => 255));

        $crashnotice->setPrimaryKey(array('crash', 'notice'));

        $crashnotice->addIndex(array('crash'));

        $crash = $schema->getTable('crash');
        $crashnotice->addForeignKeyConstraint($crash, array('crash'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
        $crashnotice->addForeignKeyConstraint($notice, array('notice'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('crashnotice');
        $schema->dropTable('notice');
    }
}
