<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190215233724 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->addColumn('crashmodule', 'string', ['length' => 255, 'notnull' => false]);
        $crash->addColumn('crashfunction', 'string', ['length' => 769, 'notnull' => false]);

        $crash->addIndex(['crashmodule', 'crashfunction']);
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->dropColumn('crashmodule');
        $crash->dropColumn('crashfunction');
    }
}
