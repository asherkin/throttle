<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20160918195311 extends AbstractMigration
{
    public function up(Schema $schema)
    {
       $this->addSql('ALTER TABLE crash CHANGE ip ip VARBINARY(16) DEFAULT NULL');
       $this->addSql('UPDATE crash SET ip = INET6_ATON(INET_NTOA(ip))'); 
    }

    public function down(Schema $schema)
    {
    }
}
