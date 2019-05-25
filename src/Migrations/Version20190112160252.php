<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190112160252 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->dropIndex('IDX_D7E8F0DFCF60E67C');

        $crashnotice = $schema->getTable('crashnotice');
        $crashnotice->dropIndex('IDX_785CF107D7E8F0DF');

        $server = $schema->getTable('server');
        $server->dropIndex('IDX_5A6DD5F6CF60E67C');

        $share = $schema->getTable('share');
        $share->dropIndex('IDX_EF069D5ACF60E67C');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->addIndex(array('owner'));

        $crashnotice = $schema->getTable('crashnotice');
        $crashnotice->addIndex(array('crash'));

        $server = $schema->getTable('server');
        $server->addIndex(array('owner'));

        $share = $schema->getTable('share');
        $share->addIndex(array('owner'));
    }
}
