<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130906055934 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $notice = $schema->createTable('notice');

        $notice->addColumn('id', 'string', ['length' => 255]);
        $notice->addColumn('severity', 'string', ['length' => 255]);
        $notice->addColumn('text', 'string', ['length' => 4095]);

        $notice->setPrimaryKey(['id']);

        $crashnotice = $schema->createTable('crashnotice');

        $crashnotice->addColumn('crash', 'string', ['length' => 12, 'fixed' => true]);
        $crashnotice->addColumn('notice', 'string', ['length' => 255]);

        $crashnotice->setPrimaryKey(['crash', 'notice']);

        $crashnotice->addIndex(['crash']);

        $crash = $schema->getTable('crash');
        $crashnotice->addForeignKeyConstraint($crash, ['crash'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $crashnotice->addForeignKeyConstraint($notice, ['notice'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('crashnotice');
        $schema->dropTable('notice');
    }
}
