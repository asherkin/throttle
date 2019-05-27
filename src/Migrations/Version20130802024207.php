<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130802024207 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $server = $schema->createTable('server');

        $server->addColumn('owner', 'bigint', ['unsigned' => true]);
        $server->addColumn('id', 'string', ['length' => 64, 'default' => '']);

        $server->setPrimaryKey(['owner', 'id']);

        $user = $schema->getTable('user');
        $server->addForeignKeyConstraint($user, ['owner'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);

        $crash = $schema->getTable('crash');
        $crash->addColumn('server', 'string', ['length' => 64, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('server');

        $schema->dropTable('server');
    }
}
