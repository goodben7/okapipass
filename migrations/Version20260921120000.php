<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agency compliance calendar: obligation types + agency obligations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_obligation_type` (
            AOT_ID VARCHAR(16) NOT NULL,
            AOT_CODE VARCHAR(40) NOT NULL,
            AOT_LABEL VARCHAR(160) NOT NULL,
            AOT_DESCRIPTION LONGTEXT DEFAULT NULL,
            AOT_CATEGORY VARCHAR(40) NOT NULL,
            AOT_DEFAULT_VALIDITY_MONTHS INT DEFAULT NULL,
            AOT_REMINDER_DAYS INT NOT NULL,
            AOT_ACTIVE TINYINT(1) NOT NULL,
            AOT_SORT_ORDER INT NOT NULL,
            AOT_CREATED_AT DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_AGENCY_OBLIGATION_TYPE_CODE (AOT_CODE),
            PRIMARY KEY(AOT_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_obligation` (
            AOB_ID VARCHAR(16) NOT NULL,
            AOB_AGENCY VARCHAR(16) NOT NULL,
            AOB_TYPE VARCHAR(16) DEFAULT NULL,
            AOB_TITLE VARCHAR(160) NOT NULL,
            AOB_REFERENCE VARCHAR(80) DEFAULT NULL,
            AOB_DUE_DATE DATE NOT NULL,
            AOB_STATUS VARCHAR(20) NOT NULL,
            AOB_REMINDER_DAYS INT NOT NULL,
            AOB_NOTES LONGTEXT DEFAULT NULL,
            AOB_COMPLETED_AT DATETIME DEFAULT NULL,
            AOB_CREATED_AT DATETIME NOT NULL,
            AOB_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_AGENCY_OBLIGATION_AGENCY (AOB_AGENCY),
            INDEX IDX_AGENCY_OBLIGATION_DUE (AOB_AGENCY, AOB_DUE_DATE),
            INDEX IDX_AGENCY_OBLIGATION_STATUS (AOB_AGENCY, AOB_STATUS),
            INDEX IDX_AGENCY_OBLIGATION_TYPE (AOB_TYPE),
            PRIMARY KEY(AOB_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_obligation` ADD CONSTRAINT FK_AGENCY_OBLIGATION_AGENCY FOREIGN KEY (AOB_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_obligation` ADD CONSTRAINT FK_AGENCY_OBLIGATION_TYPE FOREIGN KEY (AOB_TYPE) REFERENCES `agency_obligation_type` (AOT_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_obligation` DROP FOREIGN KEY FK_AGENCY_OBLIGATION_AGENCY');
        $this->addSql('ALTER TABLE `agency_obligation` DROP FOREIGN KEY FK_AGENCY_OBLIGATION_TYPE');
        $this->addSql('DROP TABLE `agency_obligation`');
        $this->addSql('DROP TABLE `agency_obligation_type`');
    }
}
