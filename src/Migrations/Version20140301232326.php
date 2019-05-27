<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20140301232326 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('output');
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->addColumn('output', 'text', ['notnull' => false]);
    }
}
