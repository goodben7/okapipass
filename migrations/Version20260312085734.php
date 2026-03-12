<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312085734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `agency` (AG_ID VARCHAR(16) NOT NULL, AG_NAME VARCHAR(120) NOT NULL, AG_EMAIL VARCHAR(180) DEFAULT NULL, AG_PHONE VARCHAR(15) DEFAULT NULL, AG_ADDRESS VARCHAR(255) DEFAULT NULL, AG_STATUS VARCHAR(10) NOT NULL, AG_USER_ID VARCHAR(16) DEFAULT NULL, AG_CREATED_AT DATETIME NOT NULL, AG_CREATED_BY VARCHAR(16) DEFAULT NULL, INDEX IDX_70C0C6E6CE98E1CE (AG_CREATED_BY), PRIMARY KEY (AG_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `agency` ADD CONSTRAINT FK_70C0C6E6CE98E1CE FOREIGN KEY (AG_CREATED_BY) REFERENCES `user` (US_ID)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `agency` DROP FOREIGN KEY FK_70C0C6E6CE98E1CE');
        $this->addSql('DROP TABLE `agency`');
    }
}
