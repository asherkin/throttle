<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230311222057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_account DROP FOREIGN KEY FK_A4948FE7A76ED395');
        $this->addSql('ALTER TABLE external_account ADD last_login DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE external_account ADD CONSTRAINT FK_A4948FE7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD contact_email_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64932175D5E FOREIGN KEY (contact_email_id) REFERENCES external_account (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64932175D5E ON user (contact_email_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE external_account DROP FOREIGN KEY FK_A4948FE7A76ED395');
        $this->addSql('ALTER TABLE external_account DROP last_login');
        $this->addSql('ALTER TABLE external_account ADD CONSTRAINT FK_A4948FE7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64932175D5E');
        $this->addSql('DROP INDEX UNIQ_8D93D64932175D5E ON user');
        $this->addSql('ALTER TABLE user DROP contact_email_id');
    }
}
