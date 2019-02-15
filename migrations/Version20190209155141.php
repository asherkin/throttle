<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190209155141 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $user = $schema->getTable('user');
        $user->addColumn('lastactive', 'datetime', array('notnull' => false));
    }

    public function down(Schema $schema)
    {
        $user = $schema->getTable('user');
        $user->dropColumn('lastactive');
    }
}
