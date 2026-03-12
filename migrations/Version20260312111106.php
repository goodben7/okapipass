<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312111106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `document` (DC_ID VARCHAR(16) NOT NULL, DC_TYPE VARCHAR(5) NOT NULL, DC_REF_NUMBER VARCHAR(255) DEFAULT NULL, DC_UPLOADED_AT DATETIME NOT NULL, DC_TITLE VARCHAR(120) DEFAULT NULL, DC_UPDATED_AT DATETIME DEFAULT NULL, DC_HOLDER_TYPE VARCHAR(255) NOT NULL, DC_HOLDER_ID VARCHAR(16) NOT NULL, DC_FILE_PATH VARCHAR(255) DEFAULT NULL, DC_FILE_SIZE INT DEFAULT NULL, DC_FILE_PATH_SECONDARY VARCHAR(255) DEFAULT NULL, DC_FILE_SIZE_SECONDARY INT DEFAULT NULL, PRIMARY KEY (DC_ID)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE `document`');
    }
}
