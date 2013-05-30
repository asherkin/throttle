<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130530032454 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $crash = $schema->createTable('crash');

        $crash->addColumn('id', 'string', array('length' => 12, 'fixed' => true));
        $crash->addColumn('timestamp', 'datetime');
        $crash->addColumn('ip', 'integer', array('unsigned' => true));
        $crash->addColumn('owner', 'string', array('length' => 255, 'notnull' => false));
        $crash->addColumn('metadata', 'string', array('length' => 4095, 'notnull' => false));
        $crash->addColumn('cmdline', 'string', array('length' => 4095, 'notnull' => false));
        $crash->addColumn('thread', 'integer', array('notnull' => false));
        $crash->addColumn('processed', 'boolean', array('default' => false));

        $crash->setPrimaryKey(array('id'));

        $crash->addIndex(array('owner'));
        $crash->addIndex(array('processed'));

        $frame = $schema->createTable('frame');

        $frame->addColumn('crash', 'string', array('length' => 12, 'fixed' => true));
        $frame->addColumn('thread', 'integer');
        $frame->addColumn('frame', 'integer');
        $frame->addColumn('module', 'string', array('length' => 255));
        $frame->addColumn('function', 'string', array('length' => 255));
        $frame->addColumn('file', 'string', array('length' => 255));
        $frame->addColumn('line', 'string', array('length' => 255));
        $frame->addColumn('offset', 'string', array('length' => 255));

        $frame->setPrimaryKey(array('crash', 'thread', 'frame'));

        $frame->addIndex(array('crash'));
        $frame->addIndex(array('crash', 'thread'));

        $frame->addForeignKeyConstraint($crash, array('crash'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));

        $module = $schema->createTable('module');

        $module->addColumn('crash', 'string', array('length' => 12, 'fixed' => true));
        $module->addColumn('name', 'string', array('length' => 255));
        $module->addColumn('identifier', 'string', array('length' => 255));

        $module->setPrimaryKey(array('crash', 'name', 'identifier'));

        $module->addIndex(array('crash'));

        $module->addForeignKeyConstraint($crash, array('crash'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
    }

    public function down(Schema $schema)
    {
        $schema->dropTable('module');
        $schema->dropTable('frame');
        $schema->dropTable('crash');
    }
}
