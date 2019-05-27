<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20190209155141 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $user = $schema->getTable('user');
        $user->addColumn('lastactive', 'datetime', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $user = $schema->getTable('user');
        $user->dropColumn('lastactive');
    }
}
