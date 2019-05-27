<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130801013430 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('INSERT IGNORE INTO user SELECT DISTINCT owner, NULL, NULL, NULL FROM crash WHERE owner IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
