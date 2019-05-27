<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20151028144951 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $frame = $schema->getTable('frame');
        $frame->addColumn('url', 'string', ['length' => 1024, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $frame = $schema->getTable('frame');
        $frame->dropColumn('url');
    }
}
