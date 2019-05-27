<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20190112195611 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        $crash = $schema->getTable('crash');
        $crash->addIndex(['ip']);
        $crash->addIndex(['failed']);

        $frame = $schema->getTable('frame');
        $frame->dropIndex('IDX_B5F83CCDD7E8F0DF');
        $frame->dropIndex('IDX_B5F83CCDD7E8F0DF31204C83');
        $frame->dropColumn('address');

        $module = $schema->getTable('module');
        $module->dropIndex('IDX_C242628D7E8F0DF');
        $module->dropIndex('IDX_C24262827FB1B8BFDBCAE17');
        $module->addIndex(['processed']);
        $module->addIndex(['present']);
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
