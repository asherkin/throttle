<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130801013430 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('INSERT IGNORE INTO user SELECT DISTINCT owner, NULL, NULL, NULL FROM crash WHERE owner IS NOT NULL');
    }

    public function down(Schema $schema)
    {
    }
}
