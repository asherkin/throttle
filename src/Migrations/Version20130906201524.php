<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

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
