<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130902135934 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE module SET processed = 1');
    }

    public function down(Schema $schema): void
    {
    }
}
