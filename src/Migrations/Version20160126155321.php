<?php

namespace ThrottleMigrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20160126155321 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE crash, (SELECT crash, thread, GROUP_CONCAT(SUBSTRING(SHA2(rendered, 256), 1, 8) ORDER BY frame ASC SEPARATOR \'\') AS hash FROM frame WHERE frame < 10 AND module != \'\' GROUP BY crash, thread) AS hashes SET crash.stackhash = hashes.hash WHERE crash.id = hashes.crash AND crash.thread = hashes.thread AND crash.processed = 1 AND crash.failed = 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE crash SET crash.stackhash = NULL');
    }
}
