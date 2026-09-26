<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 5 medium backlog: promo/loyalty caps, commissions, depots, family, driver docs, fleet planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `promotion` ADD PR_MAX_DISCOUNT_AMOUNT INT DEFAULT NULL, ADD PR_EXCLUDED_WEEKDAYS JSON DEFAULT NULL, ADD PR_MAX_USES_PER_PHONE INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `loyalty_rule` ADD LR_MAX_DISCOUNT_AMOUNT INT DEFAULT NULL, ADD LR_EXCLUDED_WEEKDAYS JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `cash_handover` ADD CH_EXPECTED_CASH INT DEFAULT 0 NOT NULL, ADD CH_VARIANCE INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `agency_transport` ADD AT_NEXT_SERVICE_KM INT DEFAULT NULL, ADD AT_NEXT_SERVICE_DATE DATE DEFAULT NULL, ADD AT_INSURANCE_EXPIRES_AT DATE DEFAULT NULL, ADD AT_TECHNICAL_CONTROL_EXPIRES_AT DATE DEFAULT NULL');

        $this->addSql('CREATE TABLE `agency_depot` (
            DP_ID VARCHAR(16) NOT NULL,
            DP_AGENCY VARCHAR(16) NOT NULL,
            DP_CODE VARCHAR(40) NOT NULL,
            DP_LABEL VARCHAR(160) NOT NULL,
            DP_ACTIVE TINYINT(1) NOT NULL,
            DP_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_AGENCY_DEPOT_AGENCY (DP_AGENCY),
            UNIQUE INDEX UNIQ_AGENCY_DEPOT_CODE (DP_AGENCY, DP_CODE),
            PRIMARY KEY(DP_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `seller_commission_rule` (
            SC_ID VARCHAR(16) NOT NULL,
            SC_AGENCY VARCHAR(16) NOT NULL,
            SC_PERIOD_TYPE VARCHAR(10) NOT NULL,
            SC_TARGET_TICKETS INT NOT NULL,
            SC_TARGET_REVENUE INT NOT NULL,
            SC_BONUS_TYPE VARCHAR(10) NOT NULL,
            SC_BONUS_VALUE INT NOT NULL,
            SC_ACTIVE TINYINT(1) NOT NULL,
            SC_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_SELLER_COMMISSION_AGENCY (SC_AGENCY),
            PRIMARY KEY(SC_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `seller_bonus_ledger` (
            SB_ID VARCHAR(16) NOT NULL,
            SB_AGENCY VARCHAR(16) NOT NULL,
            SB_SELLER VARCHAR(16) NOT NULL,
            SB_AMOUNT INT NOT NULL,
            SB_CURRENCY VARCHAR(3) NOT NULL,
            SB_PERIOD_START DATE NOT NULL,
            SB_PERIOD_END DATE NOT NULL,
            SB_RULE VARCHAR(16) NOT NULL,
            SB_STATUS VARCHAR(12) NOT NULL,
            SB_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_SELLER_BONUS_AGENCY (SB_AGENCY),
            INDEX IDX_SELLER_BONUS_SELLER (SB_SELLER),
            INDEX IDX_SELLER_BONUS_RULE (SB_RULE),
            UNIQUE INDEX UNIQ_SELLER_BONUS_PERIOD (SB_AGENCY, SB_SELLER, SB_PERIOD_START, SB_PERIOD_END, SB_RULE),
            PRIMARY KEY(SB_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `traveler_beneficiary` (
            TB_ID VARCHAR(16) NOT NULL,
            TB_OWNER VARCHAR(16) NOT NULL,
            TB_FULL_NAME VARCHAR(160) NOT NULL,
            TB_PHONE VARCHAR(20) NOT NULL,
            TB_RELATION VARCHAR(16) NOT NULL,
            TB_DATE_OF_BIRTH DATE DEFAULT NULL,
            TB_ID_DOCUMENT VARCHAR(80) DEFAULT NULL,
            TB_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_TRAVELER_BENEFICIARY_OWNER (TB_OWNER),
            PRIMARY KEY(TB_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `agency_driver_document` (
            DD_ID VARCHAR(16) NOT NULL,
            DD_AGENCY VARCHAR(16) NOT NULL,
            DD_DRIVER VARCHAR(16) NOT NULL,
            DD_TYPE VARCHAR(16) NOT NULL,
            DD_LABEL VARCHAR(160) NOT NULL,
            DD_ISSUED_AT DATE DEFAULT NULL,
            DD_EXPIRES_AT DATE DEFAULT NULL,
            DD_FILE_URL VARCHAR(512) DEFAULT NULL,
            DD_NOTES LONGTEXT DEFAULT NULL,
            DD_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_DRIVER_DOCUMENT_AGENCY (DD_AGENCY),
            INDEX IDX_DRIVER_DOCUMENT_DRIVER (DD_DRIVER),
            PRIMARY KEY(DD_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `pos_session` ADD PS_DEPOT VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `pos_session` ADD CONSTRAINT FK_POS_SESSION_DEPOT FOREIGN KEY (PS_DEPOT) REFERENCES `agency_depot` (DP_ID)');
        $this->addSql('CREATE INDEX IDX_POS_SESSION_DEPOT ON `pos_session` (PS_DEPOT)');

        $this->addSql('ALTER TABLE `accounting_daily_close` ADD AC_DEPOT VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `accounting_daily_close` ADD CONSTRAINT FK_ACCOUNTING_DAILY_CLOSE_DEPOT FOREIGN KEY (AC_DEPOT) REFERENCES `agency_depot` (DP_ID)');
        $this->addSql('CREATE INDEX IDX_ACCOUNTING_DAILY_CLOSE_DEPOT ON `accounting_daily_close` (AC_DEPOT)');

        $this->addSql('ALTER TABLE `agency_depot` ADD CONSTRAINT FK_AGENCY_DEPOT_AGENCY FOREIGN KEY (DP_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `seller_commission_rule` ADD CONSTRAINT FK_SELLER_COMMISSION_AGENCY FOREIGN KEY (SC_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `seller_bonus_ledger` ADD CONSTRAINT FK_SELLER_BONUS_AGENCY FOREIGN KEY (SB_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `seller_bonus_ledger` ADD CONSTRAINT FK_SELLER_BONUS_SELLER FOREIGN KEY (SB_SELLER) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `seller_bonus_ledger` ADD CONSTRAINT FK_SELLER_BONUS_RULE FOREIGN KEY (SB_RULE) REFERENCES `seller_commission_rule` (SC_ID)');
        $this->addSql('ALTER TABLE `traveler_beneficiary` ADD CONSTRAINT FK_TRAVELER_BENEFICIARY_OWNER FOREIGN KEY (TB_OWNER) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `agency_driver_document` ADD CONSTRAINT FK_DRIVER_DOCUMENT_AGENCY FOREIGN KEY (DD_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_driver_document` ADD CONSTRAINT FK_DRIVER_DOCUMENT_DRIVER FOREIGN KEY (DD_DRIVER) REFERENCES `agency_driver` (AD_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `pos_session` DROP FOREIGN KEY FK_POS_SESSION_DEPOT');
        $this->addSql('ALTER TABLE `accounting_daily_close` DROP FOREIGN KEY FK_ACCOUNTING_DAILY_CLOSE_DEPOT');
        $this->addSql('ALTER TABLE `pos_session` DROP INDEX IDX_POS_SESSION_DEPOT');
        $this->addSql('ALTER TABLE `accounting_daily_close` DROP INDEX IDX_ACCOUNTING_DAILY_CLOSE_DEPOT');
        $this->addSql('ALTER TABLE `pos_session` DROP PS_DEPOT');
        $this->addSql('ALTER TABLE `accounting_daily_close` DROP AC_DEPOT');

        $this->addSql('ALTER TABLE `agency_driver_document` DROP FOREIGN KEY FK_DRIVER_DOCUMENT_AGENCY');
        $this->addSql('ALTER TABLE `agency_driver_document` DROP FOREIGN KEY FK_DRIVER_DOCUMENT_DRIVER');
        $this->addSql('ALTER TABLE `traveler_beneficiary` DROP FOREIGN KEY FK_TRAVELER_BENEFICIARY_OWNER');
        $this->addSql('ALTER TABLE `seller_bonus_ledger` DROP FOREIGN KEY FK_SELLER_BONUS_AGENCY');
        $this->addSql('ALTER TABLE `seller_bonus_ledger` DROP FOREIGN KEY FK_SELLER_BONUS_SELLER');
        $this->addSql('ALTER TABLE `seller_bonus_ledger` DROP FOREIGN KEY FK_SELLER_BONUS_RULE');
        $this->addSql('ALTER TABLE `seller_commission_rule` DROP FOREIGN KEY FK_SELLER_COMMISSION_AGENCY');
        $this->addSql('ALTER TABLE `agency_depot` DROP FOREIGN KEY FK_AGENCY_DEPOT_AGENCY');

        $this->addSql('DROP TABLE `agency_driver_document`');
        $this->addSql('DROP TABLE `traveler_beneficiary`');
        $this->addSql('DROP TABLE `seller_bonus_ledger`');
        $this->addSql('DROP TABLE `seller_commission_rule`');
        $this->addSql('DROP TABLE `agency_depot`');

        $this->addSql('ALTER TABLE `agency_transport` DROP AT_NEXT_SERVICE_KM, DROP AT_NEXT_SERVICE_DATE, DROP AT_INSURANCE_EXPIRES_AT, DROP AT_TECHNICAL_CONTROL_EXPIRES_AT');
        $this->addSql('ALTER TABLE `cash_handover` DROP CH_EXPECTED_CASH, DROP CH_VARIANCE');
        $this->addSql('ALTER TABLE `loyalty_rule` DROP LR_MAX_DISCOUNT_AMOUNT, DROP LR_EXCLUDED_WEEKDAYS');
        $this->addSql('ALTER TABLE `promotion` DROP PR_MAX_DISCOUNT_AMOUNT, DROP PR_EXCLUDED_WEEKDAYS, DROP PR_MAX_USES_PER_PHONE');
    }
}
