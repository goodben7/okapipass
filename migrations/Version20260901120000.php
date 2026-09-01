<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Single grouped agency ticket for booking groups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_IS_GROUP TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_GROUP_SEATS LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_BOOKING_GROUP VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_AGENCY_TICKET_BOOKING_GROUP ON `agency_ticket` (AK_BOOKING_GROUP)');
        $this->addSql('ALTER TABLE `agency_ticket` ADD CONSTRAINT FK_AGENCY_TICKET_BOOKING_GROUP FOREIGN KEY (AK_BOOKING_GROUP) REFERENCES `agency_booking_group` (BG_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_ticket` DROP FOREIGN KEY FK_AGENCY_TICKET_BOOKING_GROUP');
        $this->addSql('DROP INDEX IDX_AGENCY_TICKET_BOOKING_GROUP ON `agency_ticket`');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_BOOKING_GROUP');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_GROUP_SEATS');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_IS_GROUP');
    }
}
