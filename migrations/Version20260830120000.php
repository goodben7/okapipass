<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Public agency online sales: offer flags, booking channel/token, payment FlexPay fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_offer` ADD AO_ONLINE_SALES TINYINT(1) DEFAULT 0 NOT NULL, ADD AO_BOOKING_HOLD_MINUTES INT DEFAULT 15 NOT NULL');

        $this->addSql('ALTER TABLE `agency_booking` ADD AB_CHANNEL VARCHAR(10) DEFAULT \'DESK\' NOT NULL, ADD AB_EXPIRES_AT DATETIME DEFAULT NULL, ADD AB_PUBLIC_TOKEN VARCHAR(64) DEFAULT NULL, ADD AB_PAYMENT_STATUS VARCHAR(12) DEFAULT \'UNPAID\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AGENCY_BOOKING_PUBLIC_TOKEN ON `agency_booking` (AB_PUBLIC_TOKEN)');
        $this->addSql('CREATE INDEX IDX_AGENCY_BOOKING_EXPIRY ON `agency_booking` (AB_STATUS, AB_EXPIRES_AT)');

        $this->addSql('ALTER TABLE `agency_payment` CHANGE AP_TICKET AP_TICKET VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_payment` ADD AP_BOOKING VARCHAR(16) DEFAULT NULL, ADD AP_CHANNEL VARCHAR(10) DEFAULT \'DESK\' NOT NULL, ADD AP_PROVIDER VARCHAR(30) DEFAULT NULL, ADD AP_PROVIDER_TX_ID VARCHAR(120) DEFAULT NULL, ADD AP_PROVIDER_RESPONSE JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE `agency_payment` ADD CONSTRAINT FK_AGENCY_PAYMENT_BOOKING FOREIGN KEY (AP_BOOKING) REFERENCES `agency_booking` (AB_ID)');
        $this->addSql('CREATE INDEX IDX_AGENCY_PAYMENT_BOOKING ON `agency_payment` (AP_BOOKING)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_payment` DROP FOREIGN KEY FK_AGENCY_PAYMENT_BOOKING');
        $this->addSql('DROP INDEX IDX_AGENCY_PAYMENT_BOOKING ON `agency_payment`');
        $this->addSql('ALTER TABLE `agency_payment` DROP AP_BOOKING, DROP AP_CHANNEL, DROP AP_PROVIDER, DROP AP_PROVIDER_TX_ID, DROP AP_PROVIDER_RESPONSE');
        $this->addSql('ALTER TABLE `agency_payment` CHANGE AP_TICKET AP_TICKET VARCHAR(16) NOT NULL');

        $this->addSql('DROP INDEX UNIQ_AGENCY_BOOKING_PUBLIC_TOKEN ON `agency_booking`');
        $this->addSql('DROP INDEX IDX_AGENCY_BOOKING_EXPIRY ON `agency_booking`');
        $this->addSql('ALTER TABLE `agency_booking` DROP AB_CHANNEL, DROP AB_EXPIRES_AT, DROP AB_PUBLIC_TOKEN, DROP AB_PAYMENT_STATUS');

        $this->addSql('ALTER TABLE `agency_offer` DROP AO_ONLINE_SALES, DROP AO_BOOKING_HOLD_MINUTES');
    }
}
