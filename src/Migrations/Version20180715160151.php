<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20180715160151 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $share = $schema->createTable('share');

        $share->addColumn('owner', 'bigint', ['unsigned' => true]);
        $share->addColumn('user', 'bigint', ['unsigned' => true]);
        $share->addColumn('accepted', 'datetime', ['notnull' => false]);

        $share->setPrimaryKey(['owner', 'user']);

        $user = $schema->getTable('user');
        $share->addForeignKeyConstraint($user, ['owner'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
        $share->addForeignKeyConstraint($user, ['user'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('share');
    }
}
