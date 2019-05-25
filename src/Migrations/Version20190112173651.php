<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190112173651 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->removeForeignKey('FK_D7E8F0DFCF60E67C5A6DD5F6');
        $crash->dropColumn('server');

        $user = $schema->getTable('user');
        $crash->addForeignKeyConstraint($user, array('owner'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
        $crash->dropIndex('IDX_D7E8F0DFCF60E67C5A6DD5F6');

        $schema->dropTable('server');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
