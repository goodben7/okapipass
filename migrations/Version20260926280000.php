<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926280000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 9 P0: nullable embarkation transport + agency_trip_assignment audit';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_embarkation` MODIFY AE_TRANSPORT VARCHAR(16) DEFAULT NULL');

        $this->addSql('CREATE TABLE `agency_trip_assignment` (
            TA_ID VARCHAR(16) NOT NULL,
            TA_AGENCY VARCHAR(16) NOT NULL,
            TA_EMBARKATION VARCHAR(16) NOT NULL,
            TA_TRANSPORT VARCHAR(16) NOT NULL,
            TA_DRIVER VARCHAR(16) DEFAULT NULL,
            TA_ASSIGNED_BY VARCHAR(16) DEFAULT NULL,
            TA_ASSIGNED_AT DATETIME NOT NULL,
            TA_UNASSIGNED_AT DATETIME DEFAULT NULL,
            TA_REASON LONGTEXT DEFAULT NULL,
            INDEX IDX_TRIP_ASSIGNMENT_AGENCY (TA_AGENCY),
            INDEX IDX_TRIP_ASSIGNMENT_EMBARKATION (TA_EMBARKATION),
            INDEX IDX_TRIP_ASSIGNMENT_TRANSPORT (TA_TRANSPORT),
            INDEX IDX_TRIP_ASSIGNMENT_DRIVER (TA_DRIVER),
            INDEX IDX_TRIP_ASSIGNMENT_ASSIGNED_BY (TA_ASSIGNED_BY),
            PRIMARY KEY(TA_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_trip_assignment` ADD CONSTRAINT FK_TRIP_ASSIGNMENT_AGENCY FOREIGN KEY (TA_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_trip_assignment` ADD CONSTRAINT FK_TRIP_ASSIGNMENT_EMBARKATION FOREIGN KEY (TA_EMBARKATION) REFERENCES `agency_embarkation` (AE_ID)');
        $this->addSql('ALTER TABLE `agency_trip_assignment` ADD CONSTRAINT FK_TRIP_ASSIGNMENT_TRANSPORT FOREIGN KEY (TA_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_trip_assignment` ADD CONSTRAINT FK_TRIP_ASSIGNMENT_DRIVER FOREIGN KEY (TA_DRIVER) REFERENCES `agency_driver` (AD_ID)');
        $this->addSql('ALTER TABLE `agency_trip_assignment` ADD CONSTRAINT FK_TRIP_ASSIGNMENT_ASSIGNED_BY FOREIGN KEY (TA_ASSIGNED_BY) REFERENCES `user` (US_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_trip_assignment` DROP FOREIGN KEY FK_TRIP_ASSIGNMENT_ASSIGNED_BY');
        $this->addSql('ALTER TABLE `agency_trip_assignment` DROP FOREIGN KEY FK_TRIP_ASSIGNMENT_DRIVER');
        $this->addSql('ALTER TABLE `agency_trip_assignment` DROP FOREIGN KEY FK_TRIP_ASSIGNMENT_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_trip_assignment` DROP FOREIGN KEY FK_TRIP_ASSIGNMENT_EMBARKATION');
        $this->addSql('ALTER TABLE `agency_trip_assignment` DROP FOREIGN KEY FK_TRIP_ASSIGNMENT_AGENCY');
        $this->addSql('DROP TABLE `agency_trip_assignment`');
        $this->addSql('ALTER TABLE `agency_embarkation` MODIFY AE_TRANSPORT VARCHAR(16) NOT NULL');
    }
}
