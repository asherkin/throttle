<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130802030115 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('INSERT IGNORE INTO server SELECT id, \'\' FROM user');
        $this->addSql('UPDATE crash SET server = \'\' WHERE owner IS NOT NULL');
    }

    public function down(Schema $schema)
    {
    }
}
