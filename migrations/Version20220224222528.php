<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220224222528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F67E3C61F9');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F67E3C61F9 FOREIGN KEY (owner_id) REFERENCES server_owner (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F67E3C61F9');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F67E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
    }
}
