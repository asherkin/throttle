<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20130812022810 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $frame = $schema->getTable('frame');
        $frame->addColumn('rendered', 'string', ['length' => 512, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $frame = $schema->getTable('frame');
        $frame->dropColumn('rendered');
    }
}
