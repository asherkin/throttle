<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130530032454 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->createTable('crash');

        $crash->addColumn('id', 'string', ['length' => 12, 'fixed' => true]);
        $crash->addColumn('timestamp', 'datetime');
        $crash->addColumn('ip', 'integer', ['unsigned' => true]);
        $crash->addColumn('owner', 'string', ['length' => 255, 'notnull' => false]);
        $crash->addColumn('metadata', 'string', ['length' => 4095, 'notnull' => false]);
        $crash->addColumn('cmdline', 'string', ['length' => 4095, 'notnull' => false]);
        $crash->addColumn('thread', 'integer', ['notnull' => false]);
        $crash->addColumn('processed', 'boolean', ['default' => false]);

        $crash->setPrimaryKey(['id']);

        $crash->addIndex(['owner']);
        $crash->addIndex(['processed']);

        $frame = $schema->createTable('frame');

        $frame->addColumn('crash', 'string', ['length' => 12, 'fixed' => true]);
        $frame->addColumn('thread', 'integer');
        $frame->addColumn('frame', 'integer');
        $frame->addColumn('module', 'string', ['length' => 255]);
        $frame->addColumn('function', 'string', ['length' => 255]);
        $frame->addColumn('file', 'string', ['length' => 255]);
        $frame->addColumn('line', 'string', ['length' => 255]);
        $frame->addColumn('offset', 'string', ['length' => 255]);

        $frame->setPrimaryKey(['crash', 'thread', 'frame']);

        $frame->addIndex(['crash']);
        $frame->addIndex(['crash', 'thread']);

        $frame->addForeignKeyConstraint($crash, ['crash'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);

        $module = $schema->createTable('module');

        $module->addColumn('crash', 'string', ['length' => 12, 'fixed' => true]);
        $module->addColumn('name', 'string', ['length' => 255]);
        $module->addColumn('identifier', 'string', ['length' => 255]);

        $module->setPrimaryKey(['crash', 'name', 'identifier']);

        $module->addIndex(['crash']);

        $module->addForeignKeyConstraint($crash, ['crash'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('module');
        $schema->dropTable('frame');
        $schema->dropTable('crash');
    }
}
