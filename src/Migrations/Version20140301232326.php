<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

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
        $crash->addColumn('output', 'text', array('notnull' => false));
    }
}
