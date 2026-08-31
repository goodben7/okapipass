<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fleet polish: rental contract payments on agency_payment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_payment` ADD AP_RENTAL_CONTRACT VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_AGENCY_PAYMENT_RENTAL ON `agency_payment` (AP_RENTAL_CONTRACT)');
        $this->addSql('ALTER TABLE `agency_payment` ADD CONSTRAINT FK_AGENCY_PAYMENT_RENTAL FOREIGN KEY (AP_RENTAL_CONTRACT) REFERENCES `agency_rental_contract` (RC_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_payment` DROP FOREIGN KEY FK_AGENCY_PAYMENT_RENTAL');
        $this->addSql('DROP INDEX IDX_AGENCY_PAYMENT_RENTAL ON `agency_payment`');
        $this->addSql('ALTER TABLE `agency_payment` DROP AP_RENTAL_CONTRACT');
    }
}
