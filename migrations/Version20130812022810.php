<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20130812022810 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $frame = $schema->getTable('frame');
        $frame->addColumn('rendered', 'string', array('length' => 512, 'notnull' => false));
    }

    public function down(Schema $schema)
    {
        $frame = $schema->getTable('frame');
        $frame->dropColumn('rendered');
    }
}
