<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190209155141 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $user = $schema->getTable('user');
        $user->addColumn('lastactive', 'datetime', array('notnull' => false));
    }

    public function down(Schema $schema): void
    {
        $user = $schema->getTable('user');
        $user->dropColumn('lastactive');
    }
}
