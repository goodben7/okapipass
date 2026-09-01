<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Group/family online bookings with multiple passengers and single payment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_booking_group` (
            BG_ID VARCHAR(16) NOT NULL,
            BG_AGENCY VARCHAR(16) NOT NULL,
            BG_OFFER VARCHAR(16) NOT NULL,
            BG_GROUP_NAME VARCHAR(120) NOT NULL,
            BG_CONTACT_PHONE VARCHAR(20) DEFAULT NULL,
            BG_TRAVEL_DATE DATE NOT NULL,
            BG_STATUS VARCHAR(12) NOT NULL,
            BG_CHANNEL VARCHAR(10) NOT NULL,
            BG_EXPIRES_AT DATETIME DEFAULT NULL,
            BG_PUBLIC_TOKEN VARCHAR(64) NOT NULL,
            BG_PAYMENT_STATUS VARCHAR(12) NOT NULL,
            BG_CREATED_AT DATETIME NOT NULL,
            BG_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_AGENCY_BOOKING_GROUP_EXPIRY (BG_STATUS, BG_EXPIRES_AT),
            UNIQUE INDEX UNIQ_AGENCY_BOOKING_GROUP_TOKEN (BG_PUBLIC_TOKEN),
            PRIMARY KEY(BG_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_booking_group` ADD CONSTRAINT FK_AGENCY_BOOKING_GROUP_AGENCY FOREIGN KEY (BG_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_booking_group` ADD CONSTRAINT FK_AGENCY_BOOKING_GROUP_OFFER FOREIGN KEY (BG_OFFER) REFERENCES `agency_offer` (AO_ID)');

        $this->addSql('ALTER TABLE `agency_booking` ADD AB_GROUP_ID VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_AGENCY_BOOKING_GROUP ON `agency_booking` (AB_GROUP_ID)');
        $this->addSql('ALTER TABLE `agency_booking` ADD CONSTRAINT FK_AGENCY_BOOKING_GROUP FOREIGN KEY (AB_GROUP_ID) REFERENCES `agency_booking_group` (BG_ID)');

        $this->addSql('ALTER TABLE `agency_payment` ADD AP_BOOKING_GROUP VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_AGENCY_PAYMENT_BOOKING_GROUP ON `agency_payment` (AP_BOOKING_GROUP)');
        $this->addSql('ALTER TABLE `agency_payment` ADD CONSTRAINT FK_AGENCY_PAYMENT_BOOKING_GROUP FOREIGN KEY (AP_BOOKING_GROUP) REFERENCES `agency_booking_group` (BG_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_payment` DROP FOREIGN KEY FK_AGENCY_PAYMENT_BOOKING_GROUP');
        $this->addSql('DROP INDEX IDX_AGENCY_PAYMENT_BOOKING_GROUP ON `agency_payment`');
        $this->addSql('ALTER TABLE `agency_payment` DROP AP_BOOKING_GROUP');

        $this->addSql('ALTER TABLE `agency_booking` DROP FOREIGN KEY FK_AGENCY_BOOKING_GROUP');
        $this->addSql('DROP INDEX IDX_AGENCY_BOOKING_GROUP ON `agency_booking`');
        $this->addSql('ALTER TABLE `agency_booking` DROP AB_GROUP_ID');

        $this->addSql('DROP TABLE `agency_booking_group`');
    }
}
