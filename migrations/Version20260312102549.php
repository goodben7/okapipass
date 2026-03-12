<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312102549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `hotel` (HO_ID VARCHAR(16) NOT NULL, HO_NAME VARCHAR(120) NOT NULL, HO_DESCRIPTION LONGTEXT DEFAULT NULL, HO_PHONE VARCHAR(15) DEFAULT NULL, HO_EMAIL VARCHAR(180) DEFAULT NULL, HO_ADDRESS VARCHAR(255) DEFAULT NULL, HO_LATITUDE NUMERIC(10, 7) DEFAULT NULL, HO_LONGITUDE NUMERIC(10, 7) DEFAULT NULL, HO_PRICE NUMERIC(17, 2) DEFAULT NULL, HO_RATING SMALLINT DEFAULT NULL, HO_STATUS VARCHAR(10) NOT NULL, HO_CREATED_AT DATETIME NOT NULL, PRIMARY KEY (HO_ID)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE `hotel`');
    }
}
