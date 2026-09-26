<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'High priority backlog: ticket cancel requests, fleet incidents, parcels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_ticket_cancel_request` (
            CR_ID VARCHAR(16) NOT NULL,
            CR_AGENCY VARCHAR(16) NOT NULL,
            CR_TICKET VARCHAR(16) NOT NULL,
            CR_REQUESTED_BY VARCHAR(16) NOT NULL,
            CR_REASON LONGTEXT NOT NULL,
            CR_STATUS VARCHAR(16) NOT NULL,
            CR_REVIEWED_BY VARCHAR(16) DEFAULT NULL,
            CR_REVIEWED_AT DATETIME DEFAULT NULL,
            CR_REVIEW_NOTES LONGTEXT DEFAULT NULL,
            CR_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_CANCEL_REQUEST_AGENCY (CR_AGENCY),
            INDEX IDX_CANCEL_REQUEST_TICKET (CR_TICKET),
            INDEX IDX_CANCEL_REQUEST_REQUESTED_BY (CR_REQUESTED_BY),
            INDEX IDX_CANCEL_REQUEST_REVIEWED_BY (CR_REVIEWED_BY),
            INDEX IDX_CANCEL_REQUEST_STATUS (CR_AGENCY, CR_STATUS),
            PRIMARY KEY(CR_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_fleet_incident` (
            FI_ID VARCHAR(16) NOT NULL,
            FI_AGENCY VARCHAR(16) NOT NULL,
            FI_TRANSPORT VARCHAR(16) NOT NULL,
            FI_DRIVER VARCHAR(16) DEFAULT NULL,
            FI_TYPE VARCHAR(16) NOT NULL,
            FI_SEVERITY VARCHAR(16) NOT NULL,
            FI_LAT DOUBLE PRECISION DEFAULT NULL,
            FI_LNG DOUBLE PRECISION DEFAULT NULL,
            FI_PHOTO_URL VARCHAR(512) DEFAULT NULL,
            FI_NOTES LONGTEXT DEFAULT NULL,
            FI_OCCURRED_AT DATETIME NOT NULL,
            FI_STATUS VARCHAR(16) NOT NULL,
            FI_CREATED_AT DATETIME NOT NULL,
            FI_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_FLEET_INCIDENT_AGENCY (FI_AGENCY),
            INDEX IDX_FLEET_INCIDENT_TRANSPORT (FI_TRANSPORT),
            INDEX IDX_FLEET_INCIDENT_DRIVER (FI_DRIVER),
            INDEX IDX_FLEET_INCIDENT_STATUS (FI_AGENCY, FI_STATUS),
            PRIMARY KEY(FI_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_parcel` (
            PL_ID VARCHAR(16) NOT NULL,
            PL_AGENCY VARCHAR(16) NOT NULL,
            PL_OFFER VARCHAR(16) DEFAULT NULL,
            PL_TRANSPORT VARCHAR(16) DEFAULT NULL,
            PL_EMBARKATION VARCHAR(16) DEFAULT NULL,
            PL_SENDER_NAME VARCHAR(160) NOT NULL,
            PL_SENDER_PHONE VARCHAR(20) NOT NULL,
            PL_RECIPIENT_NAME VARCHAR(160) NOT NULL,
            PL_RECIPIENT_PHONE VARCHAR(20) NOT NULL,
            PL_WEIGHT_KG DOUBLE PRECISION DEFAULT NULL,
            PL_FEE INT NOT NULL,
            PL_CURRENCY VARCHAR(3) NOT NULL,
            PL_STATUS VARCHAR(16) NOT NULL,
            PL_TRACKING_CODE VARCHAR(32) NOT NULL,
            PL_TRAVEL_DATE DATE DEFAULT NULL,
            PL_NOTES LONGTEXT DEFAULT NULL,
            PL_CREATED_AT DATETIME NOT NULL,
            PL_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_PARCEL_AGENCY (PL_AGENCY),
            INDEX IDX_PARCEL_OFFER (PL_OFFER),
            INDEX IDX_PARCEL_TRANSPORT (PL_TRANSPORT),
            INDEX IDX_PARCEL_EMBARKATION (PL_EMBARKATION),
            INDEX IDX_PARCEL_STATUS (PL_AGENCY, PL_STATUS),
            UNIQUE INDEX UNIQ_PARCEL_TRACKING (PL_AGENCY, PL_TRACKING_CODE),
            PRIMARY KEY(PL_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` ADD CONSTRAINT FK_CANCEL_REQUEST_AGENCY FOREIGN KEY (CR_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` ADD CONSTRAINT FK_CANCEL_REQUEST_TICKET FOREIGN KEY (CR_TICKET) REFERENCES `agency_ticket` (AK_ID)');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` ADD CONSTRAINT FK_CANCEL_REQUEST_REQUESTED_BY FOREIGN KEY (CR_REQUESTED_BY) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` ADD CONSTRAINT FK_CANCEL_REQUEST_REVIEWED_BY FOREIGN KEY (CR_REVIEWED_BY) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `agency_fleet_incident` ADD CONSTRAINT FK_FLEET_INCIDENT_AGENCY FOREIGN KEY (FI_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_fleet_incident` ADD CONSTRAINT FK_FLEET_INCIDENT_TRANSPORT FOREIGN KEY (FI_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_fleet_incident` ADD CONSTRAINT FK_FLEET_INCIDENT_DRIVER FOREIGN KEY (FI_DRIVER) REFERENCES `agency_driver` (AD_ID)');

        $this->addSql('ALTER TABLE `agency_parcel` ADD CONSTRAINT FK_PARCEL_AGENCY FOREIGN KEY (PL_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_parcel` ADD CONSTRAINT FK_PARCEL_OFFER FOREIGN KEY (PL_OFFER) REFERENCES `agency_offer` (AO_ID)');
        $this->addSql('ALTER TABLE `agency_parcel` ADD CONSTRAINT FK_PARCEL_TRANSPORT FOREIGN KEY (PL_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_parcel` ADD CONSTRAINT FK_PARCEL_EMBARKATION FOREIGN KEY (PL_EMBARKATION) REFERENCES `agency_embarkation` (AE_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_parcel` DROP FOREIGN KEY FK_PARCEL_EMBARKATION');
        $this->addSql('ALTER TABLE `agency_parcel` DROP FOREIGN KEY FK_PARCEL_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_parcel` DROP FOREIGN KEY FK_PARCEL_OFFER');
        $this->addSql('ALTER TABLE `agency_parcel` DROP FOREIGN KEY FK_PARCEL_AGENCY');
        $this->addSql('ALTER TABLE `agency_fleet_incident` DROP FOREIGN KEY FK_FLEET_INCIDENT_DRIVER');
        $this->addSql('ALTER TABLE `agency_fleet_incident` DROP FOREIGN KEY FK_FLEET_INCIDENT_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_fleet_incident` DROP FOREIGN KEY FK_FLEET_INCIDENT_AGENCY');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` DROP FOREIGN KEY FK_CANCEL_REQUEST_REVIEWED_BY');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` DROP FOREIGN KEY FK_CANCEL_REQUEST_REQUESTED_BY');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` DROP FOREIGN KEY FK_CANCEL_REQUEST_TICKET');
        $this->addSql('ALTER TABLE `agency_ticket_cancel_request` DROP FOREIGN KEY FK_CANCEL_REQUEST_AGENCY');

        $this->addSql('DROP TABLE `agency_parcel`');
        $this->addSql('DROP TABLE `agency_fleet_incident`');
        $this->addSql('DROP TABLE `agency_ticket_cancel_request`');
    }
}
