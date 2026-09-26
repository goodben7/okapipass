<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 3: fuel logs, departure checklists, work orders, baggage excess, offer baggage/noshow policy, ticket NO_SHOW + baggageKg';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_fuel_log` (
            FL_ID VARCHAR(16) NOT NULL,
            FL_AGENCY VARCHAR(16) NOT NULL,
            FL_TRANSPORT VARCHAR(16) NOT NULL,
            FL_DRIVER VARCHAR(16) DEFAULT NULL,
            FL_LITERS INT NOT NULL,
            FL_AMOUNT INT NOT NULL,
            FL_CURRENCY VARCHAR(3) NOT NULL,
            FL_ODOMETER_KM INT DEFAULT NULL,
            FL_FUELED_AT DATETIME NOT NULL,
            FL_NOTES LONGTEXT DEFAULT NULL,
            FL_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_AGENCY_FUEL_LOG_AGENCY (FL_AGENCY),
            INDEX IDX_AGENCY_FUEL_LOG_TRANSPORT (FL_TRANSPORT),
            INDEX IDX_AGENCY_FUEL_LOG_DRIVER (FL_DRIVER),
            INDEX IDX_AGENCY_FUEL_LOG_FUELED (FL_AGENCY, FL_FUELED_AT),
            PRIMARY KEY(FL_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_departure_checklist` (
            DC_ID VARCHAR(16) NOT NULL,
            DC_AGENCY VARCHAR(16) NOT NULL,
            DC_TRANSPORT VARCHAR(16) NOT NULL,
            DC_DRIVER VARCHAR(16) DEFAULT NULL,
            DC_OFFER VARCHAR(16) DEFAULT NULL,
            DC_TRAVEL_DATE DATE NOT NULL,
            DC_ODOMETER_KM INT DEFAULT NULL,
            DC_FUEL_LEVEL_PERCENT INT DEFAULT NULL,
            DC_VEHICLE_OK TINYINT(1) NOT NULL,
            DC_NOTES LONGTEXT DEFAULT NULL,
            DC_STATUS VARCHAR(16) NOT NULL,
            DC_SUBMITTED_AT DATETIME DEFAULT NULL,
            DC_CREATED_BY VARCHAR(16) DEFAULT NULL,
            DC_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_DEPARTURE_CHECKLIST_AGENCY (DC_AGENCY),
            INDEX IDX_DEPARTURE_CHECKLIST_TRANSPORT (DC_TRANSPORT),
            INDEX IDX_DEPARTURE_CHECKLIST_DRIVER (DC_DRIVER),
            INDEX IDX_DEPARTURE_CHECKLIST_OFFER (DC_OFFER),
            INDEX IDX_DEPARTURE_CHECKLIST_CREATED_BY (DC_CREATED_BY),
            INDEX IDX_DEPARTURE_CHECKLIST_DATE (DC_AGENCY, DC_TRAVEL_DATE),
            PRIMARY KEY(DC_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_work_order` (
            WO_ID VARCHAR(16) NOT NULL,
            WO_AGENCY VARCHAR(16) NOT NULL,
            WO_TRANSPORT VARCHAR(16) NOT NULL,
            WO_MAINTENANCE_CASE VARCHAR(16) DEFAULT NULL,
            WO_TITLE VARCHAR(160) NOT NULL,
            WO_DESCRIPTION LONGTEXT DEFAULT NULL,
            WO_STATUS VARCHAR(16) NOT NULL,
            WO_PARTS_COST INT DEFAULT 0 NOT NULL,
            WO_LABOR_COST INT DEFAULT 0 NOT NULL,
            WO_IMMOBILIZE TINYINT(1) NOT NULL,
            WO_VENDOR_NAME VARCHAR(120) DEFAULT NULL,
            WO_STARTED_AT DATETIME DEFAULT NULL,
            WO_COMPLETED_AT DATETIME DEFAULT NULL,
            WO_CREATED_AT DATETIME NOT NULL,
            WO_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_WORK_ORDER_AGENCY (WO_AGENCY),
            INDEX IDX_WORK_ORDER_TRANSPORT (WO_TRANSPORT),
            INDEX IDX_WORK_ORDER_MAINTENANCE (WO_MAINTENANCE_CASE),
            INDEX IDX_WORK_ORDER_STATUS (WO_AGENCY, WO_STATUS),
            PRIMARY KEY(WO_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_baggage_excess` (
            BX_ID VARCHAR(16) NOT NULL,
            BX_AGENCY VARCHAR(16) NOT NULL,
            BX_TICKET VARCHAR(16) NOT NULL,
            BX_KG INT NOT NULL,
            BX_FREE_KG_APPLIED INT NOT NULL,
            BX_EXCESS_KG INT NOT NULL,
            BX_UNIT_PRICE INT NOT NULL,
            BX_AMOUNT INT NOT NULL,
            BX_CURRENCY VARCHAR(3) NOT NULL,
            BX_STATUS VARCHAR(16) NOT NULL,
            BX_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_BAGGAGE_EXCESS_AGENCY (BX_AGENCY),
            INDEX IDX_BAGGAGE_EXCESS_TICKET (BX_TICKET),
            INDEX IDX_BAGGAGE_EXCESS_STATUS (BX_AGENCY, BX_STATUS),
            PRIMARY KEY(BX_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_fuel_log` ADD CONSTRAINT FK_FUEL_LOG_AGENCY FOREIGN KEY (FL_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_fuel_log` ADD CONSTRAINT FK_FUEL_LOG_TRANSPORT FOREIGN KEY (FL_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_fuel_log` ADD CONSTRAINT FK_FUEL_LOG_DRIVER FOREIGN KEY (FL_DRIVER) REFERENCES `agency_driver` (AD_ID)');

        $this->addSql('ALTER TABLE `agency_departure_checklist` ADD CONSTRAINT FK_DEPARTURE_CHECKLIST_AGENCY FOREIGN KEY (DC_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_departure_checklist` ADD CONSTRAINT FK_DEPARTURE_CHECKLIST_TRANSPORT FOREIGN KEY (DC_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_departure_checklist` ADD CONSTRAINT FK_DEPARTURE_CHECKLIST_DRIVER FOREIGN KEY (DC_DRIVER) REFERENCES `agency_driver` (AD_ID)');
        $this->addSql('ALTER TABLE `agency_departure_checklist` ADD CONSTRAINT FK_DEPARTURE_CHECKLIST_OFFER FOREIGN KEY (DC_OFFER) REFERENCES `agency_offer` (AO_ID)');
        $this->addSql('ALTER TABLE `agency_departure_checklist` ADD CONSTRAINT FK_DEPARTURE_CHECKLIST_CREATED_BY FOREIGN KEY (DC_CREATED_BY) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `agency_work_order` ADD CONSTRAINT FK_WORK_ORDER_AGENCY FOREIGN KEY (WO_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_work_order` ADD CONSTRAINT FK_WORK_ORDER_TRANSPORT FOREIGN KEY (WO_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_work_order` ADD CONSTRAINT FK_WORK_ORDER_MAINTENANCE FOREIGN KEY (WO_MAINTENANCE_CASE) REFERENCES `agency_maintenance_case` (MC_ID)');

        $this->addSql('ALTER TABLE `agency_baggage_excess` ADD CONSTRAINT FK_BAGGAGE_EXCESS_AGENCY FOREIGN KEY (BX_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_baggage_excess` ADD CONSTRAINT FK_BAGGAGE_EXCESS_TICKET FOREIGN KEY (BX_TICKET) REFERENCES `agency_ticket` (AK_ID)');

        $this->addSql('ALTER TABLE `agency_offer` ADD AO_BAGGAGE_FREE_KG INT DEFAULT 20 NOT NULL');
        $this->addSql('ALTER TABLE `agency_offer` ADD AO_BAGGAGE_EXCESS_PRICE_PER_KG INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `agency_offer` ADD AO_NOSHOW_RELEASE_MINUTES INT DEFAULT 30 NOT NULL');

        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_BAGGAGE_KG INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_BAGGAGE_KG');
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_NOSHOW_RELEASE_MINUTES');
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_BAGGAGE_EXCESS_PRICE_PER_KG');
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_BAGGAGE_FREE_KG');

        $this->addSql('ALTER TABLE `agency_baggage_excess` DROP FOREIGN KEY FK_BAGGAGE_EXCESS_TICKET');
        $this->addSql('ALTER TABLE `agency_baggage_excess` DROP FOREIGN KEY FK_BAGGAGE_EXCESS_AGENCY');
        $this->addSql('ALTER TABLE `agency_work_order` DROP FOREIGN KEY FK_WORK_ORDER_MAINTENANCE');
        $this->addSql('ALTER TABLE `agency_work_order` DROP FOREIGN KEY FK_WORK_ORDER_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_work_order` DROP FOREIGN KEY FK_WORK_ORDER_AGENCY');
        $this->addSql('ALTER TABLE `agency_departure_checklist` DROP FOREIGN KEY FK_DEPARTURE_CHECKLIST_CREATED_BY');
        $this->addSql('ALTER TABLE `agency_departure_checklist` DROP FOREIGN KEY FK_DEPARTURE_CHECKLIST_OFFER');
        $this->addSql('ALTER TABLE `agency_departure_checklist` DROP FOREIGN KEY FK_DEPARTURE_CHECKLIST_DRIVER');
        $this->addSql('ALTER TABLE `agency_departure_checklist` DROP FOREIGN KEY FK_DEPARTURE_CHECKLIST_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_departure_checklist` DROP FOREIGN KEY FK_DEPARTURE_CHECKLIST_AGENCY');
        $this->addSql('ALTER TABLE `agency_fuel_log` DROP FOREIGN KEY FK_FUEL_LOG_DRIVER');
        $this->addSql('ALTER TABLE `agency_fuel_log` DROP FOREIGN KEY FK_FUEL_LOG_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_fuel_log` DROP FOREIGN KEY FK_FUEL_LOG_AGENCY');

        $this->addSql('DROP TABLE `agency_baggage_excess`');
        $this->addSql('DROP TABLE `agency_work_order`');
        $this->addSql('DROP TABLE `agency_departure_checklist`');
        $this->addSql('DROP TABLE `agency_fuel_log`');
    }
}
