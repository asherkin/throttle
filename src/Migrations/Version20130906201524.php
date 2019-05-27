<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20130906201524 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $notice = $schema->getTable('notice');

        $notice->addColumn('rule', 'string', ['length' => 4095]);
    }

    public function down(Schema $schema): void
    {
        $notice = $schema->getTable('notice');

        $notice->dropColumn('rule');
    }
}
