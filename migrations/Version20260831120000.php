<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fleet phase F3: agency rental contracts linked to transports and optional drivers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_rental_contract` (
            RC_ID VARCHAR(16) NOT NULL,
            RC_AGENCY VARCHAR(16) NOT NULL,
            RC_TRANSPORT VARCHAR(16) NOT NULL,
            RC_DRIVER VARCHAR(16) DEFAULT NULL,
            RC_CLIENT_NAME VARCHAR(120) NOT NULL,
            RC_CLIENT_PHONE VARCHAR(20) NOT NULL,
            RC_CLIENT_COMPANY VARCHAR(120) DEFAULT NULL,
            RC_START_AT DATETIME NOT NULL,
            RC_END_AT DATETIME NOT NULL,
            RC_PICKUP_LOCATION VARCHAR(160) DEFAULT NULL,
            RC_DROPOFF_LOCATION VARCHAR(160) DEFAULT NULL,
            RC_DAILY_RATE INT NOT NULL,
            RC_TOTAL_AMOUNT INT NOT NULL,
            RC_DEPOSIT_AMOUNT INT DEFAULT NULL,
            RC_CURRENCY VARCHAR(3) NOT NULL,
            RC_STATUS VARCHAR(12) NOT NULL,
            RC_NOTES LONGTEXT DEFAULT NULL,
            RC_CONFIRMED_AT DATETIME DEFAULT NULL,
            RC_ACTIVATED_AT DATETIME DEFAULT NULL,
            RC_RETURNED_AT DATETIME DEFAULT NULL,
            RC_CREATED_AT DATETIME NOT NULL,
            RC_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_AGENCY_RENTAL_AGENCY (RC_AGENCY),
            INDEX IDX_AGENCY_RENTAL_TRANSPORT (RC_TRANSPORT),
            INDEX IDX_AGENCY_RENTAL_DRIVER (RC_DRIVER),
            INDEX IDX_AGENCY_RENTAL_STATUS (RC_STATUS),
            INDEX IDX_AGENCY_RENTAL_START_AT (RC_START_AT),
            INDEX IDX_AGENCY_RENTAL_END_AT (RC_END_AT),
            PRIMARY KEY(RC_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_rental_contract` ADD CONSTRAINT FK_AGENCY_RENTAL_AGENCY FOREIGN KEY (RC_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_rental_contract` ADD CONSTRAINT FK_AGENCY_RENTAL_TRANSPORT FOREIGN KEY (RC_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `agency_rental_contract` ADD CONSTRAINT FK_AGENCY_RENTAL_DRIVER FOREIGN KEY (RC_DRIVER) REFERENCES `agency_driver` (AD_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_rental_contract` DROP FOREIGN KEY FK_AGENCY_RENTAL_DRIVER');
        $this->addSql('ALTER TABLE `agency_rental_contract` DROP FOREIGN KEY FK_AGENCY_RENTAL_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_rental_contract` DROP FOREIGN KEY FK_AGENCY_RENTAL_AGENCY');
        $this->addSql('DROP TABLE `agency_rental_contract`');
    }
}
