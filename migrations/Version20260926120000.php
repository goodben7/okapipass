<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 1: OTP challenges, POS sessions, cash handovers, loyalty rules, promotions, ticket discount fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `otp_challenge` (
            OC_ID VARCHAR(16) NOT NULL,
            OC_PHONE VARCHAR(20) NOT NULL,
            OC_CODE_HASH VARCHAR(64) NOT NULL,
            OC_PURPOSE VARCHAR(40) NOT NULL,
            OC_EXPIRES_AT DATETIME NOT NULL,
            OC_CONSUMED_AT DATETIME DEFAULT NULL,
            OC_ATTEMPTS INT NOT NULL,
            OC_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_OTP_CHALLENGE_PHONE (OC_PHONE),
            INDEX IDX_OTP_CHALLENGE_PURPOSE (OC_PHONE, OC_PURPOSE),
            PRIMARY KEY(OC_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `pos_session` (
            PS_ID VARCHAR(16) NOT NULL,
            PS_AGENCY VARCHAR(16) NOT NULL,
            PS_SELLER VARCHAR(16) NOT NULL,
            PS_POINT_OF_SALE VARCHAR(80) DEFAULT NULL,
            PS_STATUS VARCHAR(12) NOT NULL,
            PS_OPENED_AT DATETIME NOT NULL,
            PS_CLOSED_AT DATETIME DEFAULT NULL,
            PS_EXPECTED_CASH INT DEFAULT 0 NOT NULL,
            PS_NOTES LONGTEXT DEFAULT NULL,
            PS_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_POS_SESSION_AGENCY (PS_AGENCY),
            INDEX IDX_POS_SESSION_SELLER (PS_SELLER),
            INDEX IDX_POS_SESSION_STATUS (PS_AGENCY, PS_STATUS),
            PRIMARY KEY(PS_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `cash_handover` (
            CH_ID VARCHAR(16) NOT NULL,
            CH_AGENCY VARCHAR(16) NOT NULL,
            CH_SESSION VARCHAR(16) NOT NULL,
            CH_SELLER VARCHAR(16) NOT NULL,
            CH_DECLARED_AMOUNT INT NOT NULL,
            CH_CURRENCY VARCHAR(3) NOT NULL,
            CH_STATUS VARCHAR(12) NOT NULL,
            CH_CONFIRMED_BY VARCHAR(16) DEFAULT NULL,
            CH_CONFIRMED_AT DATETIME DEFAULT NULL,
            CH_REJECTION_REASON VARCHAR(500) DEFAULT NULL,
            CH_NOTES LONGTEXT DEFAULT NULL,
            CH_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_CASH_HANDOVER_AGENCY (CH_AGENCY),
            INDEX IDX_CASH_HANDOVER_SESSION (CH_SESSION),
            INDEX IDX_CASH_HANDOVER_SELLER (CH_SELLER),
            INDEX IDX_CASH_HANDOVER_STATUS (CH_AGENCY, CH_STATUS),
            INDEX IDX_CASH_HANDOVER_CONFIRMED_BY (CH_CONFIRMED_BY),
            PRIMARY KEY(CH_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `loyalty_rule` (
            LR_ID VARCHAR(16) NOT NULL,
            LR_AGENCY VARCHAR(16) NOT NULL,
            LR_LABEL VARCHAR(160) NOT NULL,
            LR_ORIGIN VARCHAR(120) DEFAULT NULL,
            LR_DESTINATION VARCHAR(120) DEFAULT NULL,
            LR_OFFER VARCHAR(16) DEFAULT NULL,
            LR_TRIGGER_TYPE VARCHAR(40) NOT NULL,
            LR_WINDOW VARCHAR(40) NOT NULL,
            LR_THRESHOLD INT NOT NULL,
            LR_REWARD_TYPE VARCHAR(40) NOT NULL,
            LR_REWARD_VALUE INT NOT NULL,
            LR_STACKABLE TINYINT(1) NOT NULL,
            LR_ACTIVE TINYINT(1) NOT NULL,
            LR_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_LOYALTY_RULE_AGENCY (LR_AGENCY),
            INDEX IDX_LOYALTY_RULE_OFFER (LR_OFFER),
            INDEX IDX_LOYALTY_RULE_ACTIVE (LR_AGENCY, LR_ACTIVE),
            PRIMARY KEY(LR_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `promotion` (
            PR_ID VARCHAR(16) NOT NULL,
            PR_AGENCY VARCHAR(16) NOT NULL,
            PR_CODE VARCHAR(40) NOT NULL,
            PR_LABEL VARCHAR(160) NOT NULL,
            PR_DISCOUNT_TYPE VARCHAR(40) NOT NULL,
            PR_DISCOUNT_VALUE INT NOT NULL,
            PR_MAX_USES INT DEFAULT NULL,
            PR_USED_COUNT INT NOT NULL,
            PR_VALID_FROM DATE DEFAULT NULL,
            PR_VALID_TO DATE DEFAULT NULL,
            PR_ACTIVE TINYINT(1) NOT NULL,
            PR_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_PROMOTION_AGENCY (PR_AGENCY),
            UNIQUE INDEX UNIQ_PROMOTION_AGENCY_CODE (PR_AGENCY, PR_CODE),
            PRIMARY KEY(PR_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `pos_session` ADD CONSTRAINT FK_POS_SESSION_AGENCY FOREIGN KEY (PS_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `pos_session` ADD CONSTRAINT FK_POS_SESSION_SELLER FOREIGN KEY (PS_SELLER) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `cash_handover` ADD CONSTRAINT FK_CASH_HANDOVER_AGENCY FOREIGN KEY (CH_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `cash_handover` ADD CONSTRAINT FK_CASH_HANDOVER_SESSION FOREIGN KEY (CH_SESSION) REFERENCES `pos_session` (PS_ID)');
        $this->addSql('ALTER TABLE `cash_handover` ADD CONSTRAINT FK_CASH_HANDOVER_SELLER FOREIGN KEY (CH_SELLER) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `cash_handover` ADD CONSTRAINT FK_CASH_HANDOVER_CONFIRMED_BY FOREIGN KEY (CH_CONFIRMED_BY) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `loyalty_rule` ADD CONSTRAINT FK_LOYALTY_RULE_AGENCY FOREIGN KEY (LR_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `loyalty_rule` ADD CONSTRAINT FK_LOYALTY_RULE_OFFER FOREIGN KEY (LR_OFFER) REFERENCES `agency_offer` (AO_ID)');

        $this->addSql('ALTER TABLE `promotion` ADD CONSTRAINT FK_PROMOTION_AGENCY FOREIGN KEY (PR_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_DISCOUNT_AMOUNT INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_PROMO_CODE VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_LOYALTY_RULE VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `agency_ticket` ADD CONSTRAINT FK_AGENCY_TICKET_LOYALTY_RULE FOREIGN KEY (AK_LOYALTY_RULE) REFERENCES `loyalty_rule` (LR_ID)');
        $this->addSql('CREATE INDEX IDX_AGENCY_TICKET_LOYALTY_RULE ON `agency_ticket` (AK_LOYALTY_RULE)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_ticket` DROP FOREIGN KEY FK_AGENCY_TICKET_LOYALTY_RULE');
        $this->addSql('DROP INDEX IDX_AGENCY_TICKET_LOYALTY_RULE ON `agency_ticket`');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_LOYALTY_RULE');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_PROMO_CODE');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_DISCOUNT_AMOUNT');

        $this->addSql('ALTER TABLE `promotion` DROP FOREIGN KEY FK_PROMOTION_AGENCY');
        $this->addSql('ALTER TABLE `loyalty_rule` DROP FOREIGN KEY FK_LOYALTY_RULE_OFFER');
        $this->addSql('ALTER TABLE `loyalty_rule` DROP FOREIGN KEY FK_LOYALTY_RULE_AGENCY');
        $this->addSql('ALTER TABLE `cash_handover` DROP FOREIGN KEY FK_CASH_HANDOVER_CONFIRMED_BY');
        $this->addSql('ALTER TABLE `cash_handover` DROP FOREIGN KEY FK_CASH_HANDOVER_SELLER');
        $this->addSql('ALTER TABLE `cash_handover` DROP FOREIGN KEY FK_CASH_HANDOVER_SESSION');
        $this->addSql('ALTER TABLE `cash_handover` DROP FOREIGN KEY FK_CASH_HANDOVER_AGENCY');
        $this->addSql('ALTER TABLE `pos_session` DROP FOREIGN KEY FK_POS_SESSION_SELLER');
        $this->addSql('ALTER TABLE `pos_session` DROP FOREIGN KEY FK_POS_SESSION_AGENCY');

        $this->addSql('DROP TABLE `promotion`');
        $this->addSql('DROP TABLE `loyalty_rule`');
        $this->addSql('DROP TABLE `cash_handover`');
        $this->addSql('DROP TABLE `pos_session`');
        $this->addSql('DROP TABLE `otp_challenge`');
    }
}
