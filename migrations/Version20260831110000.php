<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fleet phase F2: agency maintenance cases linked to transports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_maintenance_case` (
            MC_ID VARCHAR(16) NOT NULL,
            MC_AGENCY VARCHAR(16) NOT NULL,
            MC_TRANSPORT VARCHAR(16) NOT NULL,
            MC_TYPE VARCHAR(20) NOT NULL,
            MC_STATUS VARCHAR(16) NOT NULL,
            MC_TITLE VARCHAR(160) NOT NULL,
            MC_DESCRIPTION LONGTEXT DEFAULT NULL,
            MC_REPORTED_AT DATETIME NOT NULL,
            MC_STARTED_AT DATETIME DEFAULT NULL,
            MC_COMPLETED_AT DATETIME DEFAULT NULL,
            MC_ODOMETER_KM INT DEFAULT NULL,
            MC_ESTIMATED_COST INT DEFAULT NULL,
            MC_ACTUAL_COST INT DEFAULT NULL,
            MC_VENDOR_NAME VARCHAR(120) DEFAULT NULL,
            MC_CREATED_AT DATETIME NOT NULL,
            MC_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_AGENCY_MAINTENANCE_AGENCY (MC_AGENCY),
            INDEX IDX_AGENCY_MAINTENANCE_TRANSPORT (MC_TRANSPORT),
            INDEX IDX_AGENCY_MAINTENANCE_STATUS (MC_STATUS),
            PRIMARY KEY(MC_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_maintenance_case` ADD CONSTRAINT FK_AGENCY_MAINTENANCE_AGENCY FOREIGN KEY (MC_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_maintenance_case` ADD CONSTRAINT FK_AGENCY_MAINTENANCE_TRANSPORT FOREIGN KEY (MC_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_maintenance_case` DROP FOREIGN KEY FK_AGENCY_MAINTENANCE_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_maintenance_case` DROP FOREIGN KEY FK_AGENCY_MAINTENANCE_AGENCY');
        $this->addSql('DROP TABLE `agency_maintenance_case`');
    }
}
