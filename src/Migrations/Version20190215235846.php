<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20190215235846 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE crash JOIN frame ON frame.crash = crash.id AND frame.thread = crash.thread AND frame.frame = 0 SET crashmodule = module, crashfunction = COALESCE(NULLIF(function, \'\'), offset)');
    }

    public function down(Schema $schema): void
    {
    }
}
