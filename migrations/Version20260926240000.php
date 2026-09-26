<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 7 partial gaps: traveler profile/share/pass, POS device, cash resolve, premium seats, schedule history, fleet reports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD US_ID_DOCUMENT VARCHAR(80) DEFAULT NULL, ADD US_EMERGENCY_CONTACT_NAME VARCHAR(120) DEFAULT NULL, ADD US_EMERGENCY_CONTACT_PHONE VARCHAR(20) DEFAULT NULL, ADD US_PREFERENCES JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_SHARE_TOKEN VARCHAR(64) DEFAULT NULL, ADD AK_SHARE_TOKEN_EXPIRES_AT DATETIME DEFAULT NULL, ADD AK_TRAVELER_PASS VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AGENCY_TICKET_SHARE_TOKEN ON `agency_ticket` (AK_SHARE_TOKEN)');
        $this->addSql('ALTER TABLE `pos_session` ADD PS_DEVICE_ID VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_staff_member` ADD SM_PIN_HASH VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `cash_handover` ADD CH_RESOLUTION_JUSTIFICATION VARCHAR(500) DEFAULT NULL, ADD CH_RESOLVED_AT DATETIME DEFAULT NULL, ADD CH_RESOLVED_BY VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `promotion` ADD PR_EXCLUDED_SEAT_CLASSES JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `loyalty_rule` ADD LR_EXCLUDED_SEAT_CLASSES JSON DEFAULT NULL');

        $this->addSql('CREATE TABLE `agency_offer_schedule_history` (
            SH_ID VARCHAR(16) NOT NULL,
            SH_OFFER VARCHAR(16) NOT NULL,
            SH_DEPARTURE_TIME VARCHAR(5) NOT NULL,
            SH_EFFECTIVE_FROM DATETIME NOT NULL,
            SH_EFFECTIVE_TO DATETIME DEFAULT NULL,
            SH_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_OFFER_SCHEDULE_HISTORY_OFFER (SH_OFFER),
            PRIMARY KEY(SH_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_ticket` ADD CONSTRAINT FK_AGENCY_TICKET_TRAVELER_PASS FOREIGN KEY (AK_TRAVELER_PASS) REFERENCES `traveler_pass` (TP_ID)');
        $this->addSql('ALTER TABLE `cash_handover` ADD CONSTRAINT FK_CASH_HANDOVER_RESOLVED_BY FOREIGN KEY (CH_RESOLVED_BY) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `agency_offer_schedule_history` ADD CONSTRAINT FK_OFFER_SCHEDULE_HISTORY_OFFER FOREIGN KEY (SH_OFFER) REFERENCES `agency_offer` (AO_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_offer_schedule_history` DROP FOREIGN KEY FK_OFFER_SCHEDULE_HISTORY_OFFER');
        $this->addSql('ALTER TABLE `cash_handover` DROP FOREIGN KEY FK_CASH_HANDOVER_RESOLVED_BY');
        $this->addSql('ALTER TABLE `agency_ticket` DROP FOREIGN KEY FK_AGENCY_TICKET_TRAVELER_PASS');
        $this->addSql('DROP TABLE `agency_offer_schedule_history`');
        $this->addSql('ALTER TABLE `loyalty_rule` DROP LR_EXCLUDED_SEAT_CLASSES');
        $this->addSql('ALTER TABLE `promotion` DROP PR_EXCLUDED_SEAT_CLASSES');
        $this->addSql('ALTER TABLE `cash_handover` DROP CH_RESOLUTION_JUSTIFICATION, DROP CH_RESOLVED_AT, DROP CH_RESOLVED_BY');
        $this->addSql('ALTER TABLE `agency_staff_member` DROP SM_PIN_HASH');
        $this->addSql('ALTER TABLE `pos_session` DROP PS_DEVICE_ID');
        $this->addSql('DROP INDEX UNIQ_AGENCY_TICKET_SHARE_TOKEN ON `agency_ticket`');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_SHARE_TOKEN, DROP AK_SHARE_TOKEN_EXPIRES_AT, DROP AK_TRAVELER_PASS');
        $this->addSql('ALTER TABLE `user` DROP US_ID_DOCUMENT, DROP US_EMERGENCY_CONTACT_NAME, DROP US_EMERGENCY_CONTACT_PHONE, DROP US_PREFERENCES');
    }
}
