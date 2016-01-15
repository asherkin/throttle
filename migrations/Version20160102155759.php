<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20160102155759 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->addColumn('base', 'integer', array('unsigned' => true, 'notnull' => false));

        $frame = $schema->getTable('frame');
        $frame->addColumn('address', 'integer', array('unsigned' => true, 'notnull' => false));
    }

    public function down(Schema $schema)
    {
        $module = $schema->getTable('module');
        $module->dropColumn('base');

        $frame = $schema->getTable('frame');
        $frame->dropColumn('address');
    }
}
