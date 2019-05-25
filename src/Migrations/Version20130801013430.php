<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

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
