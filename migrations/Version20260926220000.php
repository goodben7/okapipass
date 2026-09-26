<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 6 low-priority backlog: RBAC support cols, audit, idempotency, webhooks, blacklist, minor rules, refund policy, QR token, price history, insurance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency` ADD AG_CANCEL_WINDOW_HOURS INT DEFAULT 2 NOT NULL, ADD AG_REFUND_FEE_PERCENT INT DEFAULT 0 NOT NULL, ADD AG_RESCHEDULE_FEE_FLAT INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `agency_offer` ADD AO_MIN_UNACCOMPANIED_AGE INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_PASSENGER_DOB DATE DEFAULT NULL, ADD AK_ESCORT_TICKET_ID VARCHAR(16) DEFAULT NULL, ADD AK_ESCORT_NAME VARCHAR(120) DEFAULT NULL, ADD AK_QR_TOKEN VARCHAR(64) DEFAULT NULL, ADD AK_QR_TOKEN_EXPIRES_AT DATETIME DEFAULT NULL, ADD AK_QR_TOKEN_USED_AT DATETIME DEFAULT NULL, ADD AK_INSURANCE_OPTED TINYINT(1) DEFAULT 0 NOT NULL, ADD AK_INSURANCE_FEE INT DEFAULT 0 NOT NULL, ADD AK_RESCHEDULE_FEE INT DEFAULT 0 NOT NULL');

        $this->addSql('CREATE TABLE `agency_audit_log` (
            AL_ID VARCHAR(16) NOT NULL,
            AL_AGENCY VARCHAR(16) NOT NULL,
            AL_ACTOR VARCHAR(16) DEFAULT NULL,
            AL_ACTION VARCHAR(80) NOT NULL,
            AL_ENTITY_TYPE VARCHAR(80) NOT NULL,
            AL_ENTITY_ID VARCHAR(40) NOT NULL,
            AL_META JSON DEFAULT NULL,
            AL_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_AGENCY_AUDIT_LOG_AGENCY (AL_AGENCY),
            INDEX IDX_AGENCY_AUDIT_LOG_ACTION (AL_ACTION),
            INDEX IDX_AGENCY_AUDIT_LOG_CREATED (AL_CREATED_AT),
            PRIMARY KEY(AL_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `idempotency_record` (
            IK_ID VARCHAR(16) NOT NULL,
            IK_AGENCY VARCHAR(16) DEFAULT NULL,
            IK_SCOPE VARCHAR(40) NOT NULL,
            IK_KEY_HASH VARCHAR(64) NOT NULL,
            IK_RESPONSE_STATUS INT NOT NULL,
            IK_RESPONSE_BODY JSON DEFAULT NULL,
            IK_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_IDEMPOTENCY_AGENCY (IK_AGENCY),
            UNIQUE INDEX UNIQ_IDEMPOTENCY_SCOPE_KEY (IK_SCOPE, IK_KEY_HASH),
            PRIMARY KEY(IK_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_webhook_subscription` (
            WH_ID VARCHAR(16) NOT NULL,
            WH_AGENCY VARCHAR(16) NOT NULL,
            WH_URL VARCHAR(512) NOT NULL,
            WH_SECRET VARCHAR(120) NOT NULL,
            WH_EVENTS JSON NOT NULL,
            WH_ACTIVE TINYINT(1) NOT NULL,
            WH_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_AGENCY_WEBHOOK_AGENCY (WH_AGENCY),
            PRIMARY KEY(WH_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_blacklist_entry` (
            BL_ID VARCHAR(16) NOT NULL,
            BL_AGENCY VARCHAR(16) NOT NULL,
            BL_TYPE VARCHAR(20) NOT NULL,
            BL_VALUE VARCHAR(120) NOT NULL,
            BL_REASON VARCHAR(255) DEFAULT NULL,
            BL_ACTIVE TINYINT(1) NOT NULL,
            BL_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_AGENCY_BLACKLIST_AGENCY (BL_AGENCY),
            INDEX IDX_AGENCY_BLACKLIST_LOOKUP (BL_AGENCY, BL_TYPE, BL_VALUE),
            PRIMARY KEY(BL_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_offer_price_history` (
            PH_ID VARCHAR(16) NOT NULL,
            PH_OFFER VARCHAR(16) NOT NULL,
            PH_TICKET_PRICE INT NOT NULL,
            PH_EFFECTIVE_FROM DATETIME NOT NULL,
            PH_EFFECTIVE_TO DATETIME DEFAULT NULL,
            PH_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_OFFER_PRICE_HISTORY_OFFER (PH_OFFER),
            PRIMARY KEY(PH_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `agency_audit_log` ADD CONSTRAINT FK_AGENCY_AUDIT_LOG_AGENCY FOREIGN KEY (AL_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_audit_log` ADD CONSTRAINT FK_AGENCY_AUDIT_LOG_ACTOR FOREIGN KEY (AL_ACTOR) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `idempotency_record` ADD CONSTRAINT FK_IDEMPOTENCY_AGENCY FOREIGN KEY (IK_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_webhook_subscription` ADD CONSTRAINT FK_AGENCY_WEBHOOK_AGENCY FOREIGN KEY (WH_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_blacklist_entry` ADD CONSTRAINT FK_AGENCY_BLACKLIST_AGENCY FOREIGN KEY (BL_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_offer_price_history` ADD CONSTRAINT FK_OFFER_PRICE_HISTORY_OFFER FOREIGN KEY (PH_OFFER) REFERENCES `agency_offer` (AO_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_offer_price_history` DROP FOREIGN KEY FK_OFFER_PRICE_HISTORY_OFFER');
        $this->addSql('ALTER TABLE `agency_blacklist_entry` DROP FOREIGN KEY FK_AGENCY_BLACKLIST_AGENCY');
        $this->addSql('ALTER TABLE `agency_webhook_subscription` DROP FOREIGN KEY FK_AGENCY_WEBHOOK_AGENCY');
        $this->addSql('ALTER TABLE `idempotency_record` DROP FOREIGN KEY FK_IDEMPOTENCY_AGENCY');
        $this->addSql('ALTER TABLE `agency_audit_log` DROP FOREIGN KEY FK_AGENCY_AUDIT_LOG_ACTOR');
        $this->addSql('ALTER TABLE `agency_audit_log` DROP FOREIGN KEY FK_AGENCY_AUDIT_LOG_AGENCY');

        $this->addSql('DROP TABLE `agency_offer_price_history`');
        $this->addSql('DROP TABLE `agency_blacklist_entry`');
        $this->addSql('DROP TABLE `agency_webhook_subscription`');
        $this->addSql('DROP TABLE `idempotency_record`');
        $this->addSql('DROP TABLE `agency_audit_log`');

        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_PASSENGER_DOB, DROP AK_ESCORT_TICKET_ID, DROP AK_ESCORT_NAME, DROP AK_QR_TOKEN, DROP AK_QR_TOKEN_EXPIRES_AT, DROP AK_QR_TOKEN_USED_AT, DROP AK_INSURANCE_OPTED, DROP AK_INSURANCE_FEE, DROP AK_RESCHEDULE_FEE');
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_MIN_UNACCOMPANIED_AGE');
        $this->addSql('ALTER TABLE `agency` DROP AG_CANCEL_WINDOW_HOURS, DROP AG_REFUND_FEE_PERCENT, DROP AG_RESCHEDULE_FEE_FLAT');
    }
}
