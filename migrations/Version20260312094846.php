<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312094846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `trip` (TR_ID VARCHAR(16) NOT NULL, TR_LABEL VARCHAR(120) NOT NULL, TR_DEPARTURE_TIME DATETIME NOT NULL, TR_ARRIVAL_TIME DATETIME NOT NULL, TR_PRICE NUMERIC(17, 2) NOT NULL, TR_STATUS VARCHAR(10) NOT NULL, TR_CREATED_AT DATETIME NOT NULL, TR_AGENCY VARCHAR(16) NOT NULL, TR_DEPARTURE VARCHAR(16) NOT NULL, TR_ARRIVAL VARCHAR(16) NOT NULL, INDEX IDX_7656F53B3AD3C9F2 (TR_AGENCY), INDEX IDX_7656F53BCB47D20C (TR_DEPARTURE), INDEX IDX_7656F53B7A1BBB0E (TR_ARRIVAL), PRIMARY KEY (TR_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `trip` ADD CONSTRAINT FK_7656F53B3AD3C9F2 FOREIGN KEY (TR_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `trip` ADD CONSTRAINT FK_7656F53BCB47D20C FOREIGN KEY (TR_DEPARTURE) REFERENCES `checkpoint` (CP_ID)');
        $this->addSql('ALTER TABLE `trip` ADD CONSTRAINT FK_7656F53B7A1BBB0E FOREIGN KEY (TR_ARRIVAL) REFERENCES `checkpoint` (CP_ID)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `trip` DROP FOREIGN KEY FK_7656F53B3AD3C9F2');
        $this->addSql('ALTER TABLE `trip` DROP FOREIGN KEY FK_7656F53BCB47D20C');
        $this->addSql('ALTER TABLE `trip` DROP FOREIGN KEY FK_7656F53B7A1BBB0E');
        $this->addSql('DROP TABLE `trip`');
    }
}
