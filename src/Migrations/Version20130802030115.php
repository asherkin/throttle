<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130802030115 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('INSERT IGNORE INTO server SELECT id, \'\' FROM user');
        $this->addSql('UPDATE crash SET server = \'\' WHERE owner IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
