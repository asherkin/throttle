<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130731153146 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE crash SET owner = NULL WHERE owner = 0');
        $this->addSql('ALTER TABLE crash CHANGE owner owner BIGINT UNSIGNED NULL DEFAULT NULL');

        $crash = $schema->createTable('user');

        $crash->addColumn('id', 'bigint', array('unsigned' => true));
        $crash->addColumn('name', 'string', array('length' => 255, 'notnull' => false));
        $crash->addColumn('avatar', 'string', array('length' => 255, 'notnull' => false));
        $crash->addColumn('updated', 'datetime', array('notnull' => false));

        $crash->setPrimaryKey(array('id'));

        $crash->addIndex(array('updated'));
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user');

        $this->addSql('ALTER TABLE crash CHANGE owner owner VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL');
    }
}
