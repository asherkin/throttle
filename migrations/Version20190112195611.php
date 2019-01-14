<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190112195611 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $crash = $schema->getTable('crash');
        $crash->addIndex(array('ip'));
        $crash->addIndex(array('failed'));

        $frame = $schema->getTable('frame');
        $frame->dropIndex('IDX_B5F83CCDD7E8F0DF');
        $frame->dropIndex('IDX_B5F83CCDD7E8F0DF31204C83');
        $frame->dropColumn('address');

        $module = $schema->getTable('module');
        $module->dropIndex('IDX_C242628D7E8F0DF');
        $module->dropIndex('IDX_C24262827FB1B8BFDBCAE17');
        $module->addIndex(array('processed'));
        $module->addIndex(array('present'));
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->throwIrreversibleMigrationException();
    }
}
