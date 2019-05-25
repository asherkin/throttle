<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190107164838 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->addColumn('lastview', 'datetime', array('notnull' => false));

        $crash->addIndex(array('lastview'));
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('lastview');
    }
}
