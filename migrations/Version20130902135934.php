<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130902135934 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('UPDATE module SET processed = 1');
    }

    public function down(Schema $schema)
    {
    }
}
