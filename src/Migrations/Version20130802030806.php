<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130802030806 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->addIndex(['owner', 'server']);

        $server = $schema->getTable('server');
        $crash->addForeignKeyConstraint($server, ['owner', 'server'], ['owner', 'id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');

        $crash->removeForeignKey('FK_D7E8F0DFCF60E67C5A6DD5F6');
        $crash->dropIndex('IDX_D7E8F0DFCF60E67C5A6DD5F6');
    }
}
