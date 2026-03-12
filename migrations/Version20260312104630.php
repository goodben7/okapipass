<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312104630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `tourist_site` (TS_ID VARCHAR(16) NOT NULL, TS_NAME VARCHAR(120) NOT NULL, TS_DESCRIPTION LONGTEXT DEFAULT NULL, TS_ADDRESS VARCHAR(255) DEFAULT NULL, TS_LATITUDE NUMERIC(10, 7) DEFAULT NULL, TS_LONGITUDE NUMERIC(10, 7) DEFAULT NULL, TS_ENTRY_PRICE NUMERIC(17, 2) DEFAULT NULL, TS_OPENING_HOURS LONGTEXT DEFAULT NULL, TS_STATUS VARCHAR(10) NOT NULL, TS_CREATED_AT DATETIME NOT NULL, TS_CITY VARCHAR(16) DEFAULT NULL, INDEX IDX_21530B8640250005 (TS_CITY), PRIMARY KEY (TS_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `tourist_site` ADD CONSTRAINT FK_21530B8640250005 FOREIGN KEY (TS_CITY) REFERENCES `checkpoint` (CP_ID)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `tourist_site` DROP FOREIGN KEY FK_21530B8640250005');
        $this->addSql('DROP TABLE `tourist_site`');
    }
}
