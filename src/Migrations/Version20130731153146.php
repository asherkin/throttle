<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130731153146 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE crash SET owner = NULL WHERE owner = 0');
        $this->addSql('ALTER TABLE crash CHANGE owner owner BIGINT UNSIGNED NULL DEFAULT NULL');

        $crash = $schema->createTable('user');

        $crash->addColumn('id', 'bigint', ['unsigned' => true]);
        $crash->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $crash->addColumn('avatar', 'string', ['length' => 255, 'notnull' => false]);
        $crash->addColumn('updated', 'datetime', ['notnull' => false]);

        $crash->setPrimaryKey(['id']);

        $crash->addIndex(['updated']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user');

        $this->addSql('ALTER TABLE crash CHANGE owner owner VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL');
    }
}
