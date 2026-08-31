<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fleet phase F1: agency drivers + optional driver on embarkations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_driver` (
            AD_ID VARCHAR(16) NOT NULL,
            AD_AGENCY VARCHAR(16) NOT NULL,
            AD_FULL_NAME VARCHAR(120) NOT NULL,
            AD_PHONE VARCHAR(20) NOT NULL,
            AD_LICENSE_NUMBER VARCHAR(40) NOT NULL,
            AD_LICENSE_EXPIRES_AT DATE DEFAULT NULL,
            AD_STATUS VARCHAR(12) NOT NULL,
            AD_NOTES LONGTEXT DEFAULT NULL,
            AD_CREATED_AT DATETIME NOT NULL,
            AD_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_AGENCY_DRIVER_AGENCY (AD_AGENCY),
            UNIQUE INDEX UNIQ_AGENCY_DRIVER_LICENSE (AD_AGENCY, AD_LICENSE_NUMBER),
            PRIMARY KEY(AD_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_driver` ADD CONSTRAINT FK_AGENCY_DRIVER_AGENCY FOREIGN KEY (AD_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `agency_embarkation` ADD AE_DRIVER VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_embarkation` ADD CONSTRAINT FK_AGENCY_EMBARKATION_DRIVER FOREIGN KEY (AE_DRIVER) REFERENCES `agency_driver` (AD_ID)');
        $this->addSql('CREATE INDEX IDX_AGENCY_EMBARKATION_DRIVER ON `agency_embarkation` (AE_DRIVER)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_embarkation` DROP FOREIGN KEY FK_AGENCY_EMBARKATION_DRIVER');
        $this->addSql('DROP INDEX IDX_AGENCY_EMBARKATION_DRIVER ON `agency_embarkation`');
        $this->addSql('ALTER TABLE `agency_embarkation` DROP AE_DRIVER');

        $this->addSql('ALTER TABLE `agency_driver` DROP FOREIGN KEY FK_AGENCY_DRIVER_AGENCY');
        $this->addSql('DROP TABLE `agency_driver`');
    }
}
