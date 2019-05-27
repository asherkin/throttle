<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130805150427 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->addIndex(['timestamp']);
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->dropIndex('IDX_D7E8F0DFA5D6E63E');
    }
}
