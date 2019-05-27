<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20190107164838 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->addColumn('lastview', 'datetime', ['notnull' => false]);

        $crash->addIndex(['lastview']);
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->dropColumn('lastview');
    }
}
