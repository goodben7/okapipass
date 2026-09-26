<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 2: traveler wallet/ledger/topup, multi-day passes, preorders, accounting journal/close, loyalty accounts/ledger, surprise pools';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `traveler_wallet` (
            WA_ID VARCHAR(16) NOT NULL,
            WA_USER VARCHAR(16) NOT NULL,
            WA_CURRENCY VARCHAR(3) DEFAULT \'CDF\' NOT NULL,
            WA_BALANCE INT DEFAULT 0 NOT NULL,
            WA_CREATED_AT DATETIME NOT NULL,
            WA_UPDATED_AT DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_TRAVELER_WALLET_USER_CURRENCY (WA_USER, WA_CURRENCY),
            INDEX IDX_TRAVELER_WALLET_USER (WA_USER),
            PRIMARY KEY(WA_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `wallet_ledger` (
            WL_ID VARCHAR(16) NOT NULL,
            WL_WALLET VARCHAR(16) NOT NULL,
            WL_TYPE VARCHAR(20) NOT NULL,
            WL_AMOUNT INT NOT NULL,
            WL_BALANCE_AFTER INT NOT NULL,
            WL_CURRENCY VARCHAR(3) NOT NULL,
            WL_REFERENCE VARCHAR(80) DEFAULT NULL,
            WL_LABEL VARCHAR(160) DEFAULT NULL,
            WL_META JSON DEFAULT NULL,
            WL_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_WALLET_LEDGER_WALLET (WL_WALLET),
            INDEX IDX_WALLET_LEDGER_WALLET_CREATED (WL_WALLET, WL_CREATED_AT),
            PRIMARY KEY(WL_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `wallet_topup` (
            WT_ID VARCHAR(16) NOT NULL,
            WT_WALLET VARCHAR(16) NOT NULL,
            WT_USER VARCHAR(16) NOT NULL,
            WT_AMOUNT INT NOT NULL,
            WT_CURRENCY VARCHAR(3) NOT NULL,
            WT_STATUS VARCHAR(20) NOT NULL,
            WT_METHOD VARCHAR(20) NOT NULL,
            WT_PHONE VARCHAR(20) DEFAULT NULL,
            WT_PROVIDER VARCHAR(20) DEFAULT NULL,
            WT_PROVIDER_TX VARCHAR(80) DEFAULT NULL,
            WT_PROVIDER_RESPONSE JSON DEFAULT NULL,
            WT_PAID_AT DATETIME DEFAULT NULL,
            WT_CREATED_AT DATETIME NOT NULL,
            WT_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_WALLET_TOPUP_WALLET (WT_WALLET),
            INDEX IDX_WALLET_TOPUP_USER (WT_USER),
            INDEX IDX_WALLET_TOPUP_STATUS (WT_STATUS),
            INDEX IDX_WALLET_TOPUP_PROVIDER_TX (WT_PROVIDER_TX),
            PRIMARY KEY(WT_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `traveler_pass_product` (
            PP_ID VARCHAR(16) NOT NULL,
            PP_AGENCY VARCHAR(16) NOT NULL,
            PP_CODE VARCHAR(40) NOT NULL,
            PP_LABEL VARCHAR(160) NOT NULL,
            PP_ORIGIN VARCHAR(120) DEFAULT NULL,
            PP_DESTINATION VARCHAR(120) DEFAULT NULL,
            PP_TRIPS_ALLOWED INT DEFAULT NULL,
            PP_VALIDITY_DAYS INT NOT NULL,
            PP_PRICE INT NOT NULL,
            PP_CURRENCY VARCHAR(3) NOT NULL,
            PP_ACTIVE TINYINT(1) NOT NULL,
            PP_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_TRAVELER_PASS_PRODUCT_AGENCY (PP_AGENCY),
            UNIQUE INDEX UNIQ_TRAVELER_PASS_PRODUCT_AGENCY_CODE (PP_AGENCY, PP_CODE),
            PRIMARY KEY(PP_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `traveler_pass` (
            TP_ID VARCHAR(16) NOT NULL,
            TP_USER VARCHAR(16) NOT NULL,
            TP_PRODUCT VARCHAR(16) NOT NULL,
            TP_AGENCY VARCHAR(16) NOT NULL,
            TP_STATUS VARCHAR(20) NOT NULL,
            TP_TRIPS_REMAINING INT DEFAULT NULL,
            TP_VALID_FROM DATE NOT NULL,
            TP_VALID_TO DATE NOT NULL,
            TP_PURCHASE_PRICE INT NOT NULL,
            TP_CURRENCY VARCHAR(3) NOT NULL,
            TP_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_TRAVELER_PASS_USER (TP_USER),
            INDEX IDX_TRAVELER_PASS_PRODUCT (TP_PRODUCT),
            INDEX IDX_TRAVELER_PASS_AGENCY (TP_AGENCY),
            INDEX IDX_TRAVELER_PASS_STATUS (TP_AGENCY, TP_STATUS),
            PRIMARY KEY(TP_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `traveler_preorder` (
            PO_ID VARCHAR(16) NOT NULL,
            PO_USER VARCHAR(16) NOT NULL,
            PO_AGENCY VARCHAR(16) NOT NULL,
            PO_OFFER VARCHAR(16) NOT NULL,
            PO_STATUS VARCHAR(20) NOT NULL,
            PO_PASSENGER_NAME VARCHAR(160) NOT NULL,
            PO_PASSENGER_ID VARCHAR(80) NOT NULL,
            PO_PASSENGER_PHONE VARCHAR(20) NOT NULL,
            PO_SEAT_NUMBER VARCHAR(20) DEFAULT NULL,
            PO_TRAVEL_DATE_PREFERRED DATE DEFAULT NULL,
            PO_HOLD_UNTIL DATETIME NOT NULL,
            PO_BOOKING VARCHAR(16) DEFAULT NULL,
            PO_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_TRAVELER_PREORDER_USER (PO_USER),
            INDEX IDX_TRAVELER_PREORDER_AGENCY (PO_AGENCY),
            INDEX IDX_TRAVELER_PREORDER_OFFER (PO_OFFER),
            INDEX IDX_TRAVELER_PREORDER_BOOKING (PO_BOOKING),
            INDEX IDX_TRAVELER_PREORDER_STATUS (PO_AGENCY, PO_STATUS),
            PRIMARY KEY(PO_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `accounting_journal` (
            AJ_ID VARCHAR(16) NOT NULL,
            AJ_AGENCY VARCHAR(16) NOT NULL,
            AJ_ENTRY_DATE DATE NOT NULL,
            AJ_ACCOUNT VARCHAR(40) NOT NULL,
            AJ_DIRECTION VARCHAR(10) NOT NULL,
            AJ_AMOUNT INT NOT NULL,
            AJ_CURRENCY VARCHAR(3) NOT NULL,
            AJ_SOURCE_TYPE VARCHAR(40) NOT NULL,
            AJ_SOURCE_ID VARCHAR(16) DEFAULT NULL,
            AJ_LABEL VARCHAR(160) NOT NULL,
            AJ_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_ACCOUNTING_JOURNAL_AGENCY (AJ_AGENCY),
            INDEX IDX_ACCOUNTING_JOURNAL_AGENCY_DATE (AJ_AGENCY, AJ_ENTRY_DATE),
            INDEX IDX_ACCOUNTING_JOURNAL_SOURCE (AJ_SOURCE_TYPE, AJ_SOURCE_ID),
            PRIMARY KEY(AJ_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `accounting_daily_close` (
            AC_ID VARCHAR(16) NOT NULL,
            AC_AGENCY VARCHAR(16) NOT NULL,
            AC_CLOSE_DATE DATE NOT NULL,
            AC_STATUS VARCHAR(12) NOT NULL,
            AC_SALES_TOTAL INT NOT NULL,
            AC_CASH_TOTAL INT NOT NULL,
            AC_MM_TOTAL INT NOT NULL,
            AC_CARD_TOTAL INT NOT NULL,
            AC_CURRENCY VARCHAR(3) NOT NULL,
            AC_NOTES LONGTEXT DEFAULT NULL,
            AC_CLOSED_BY VARCHAR(16) DEFAULT NULL,
            AC_CLOSED_AT DATETIME DEFAULT NULL,
            AC_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_ACCOUNTING_DAILY_CLOSE_AGENCY (AC_AGENCY),
            INDEX IDX_ACCOUNTING_DAILY_CLOSE_CLOSED_BY (AC_CLOSED_BY),
            UNIQUE INDEX UNIQ_ACCOUNTING_DAILY_CLOSE_AGENCY_DATE (AC_AGENCY, AC_CLOSE_DATE),
            PRIMARY KEY(AC_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `loyalty_account` (
            LA_ID VARCHAR(16) NOT NULL,
            LA_AGENCY VARCHAR(16) NOT NULL,
            LA_PHONE VARCHAR(20) NOT NULL,
            LA_POINTS INT DEFAULT 0 NOT NULL,
            LA_CREATED_AT DATETIME NOT NULL,
            LA_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_LOYALTY_ACCOUNT_AGENCY (LA_AGENCY),
            UNIQUE INDEX UNIQ_LOYALTY_ACCOUNT_AGENCY_PHONE (LA_AGENCY, LA_PHONE),
            PRIMARY KEY(LA_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `loyalty_point_ledger` (
            LP_ID VARCHAR(16) NOT NULL,
            LP_ACCOUNT VARCHAR(16) NOT NULL,
            LP_DELTA INT NOT NULL,
            LP_BALANCE_AFTER INT NOT NULL,
            LP_REASON VARCHAR(40) NOT NULL,
            LP_TICKET VARCHAR(16) DEFAULT NULL,
            LP_LABEL VARCHAR(160) DEFAULT NULL,
            LP_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_LOYALTY_POINT_LEDGER_ACCOUNT (LP_ACCOUNT),
            INDEX IDX_LOYALTY_POINT_LEDGER_TICKET (LP_TICKET),
            PRIMARY KEY(LP_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `surprise_pool` (
            SP_ID VARCHAR(16) NOT NULL,
            SP_AGENCY VARCHAR(16) NOT NULL,
            SP_LABEL VARCHAR(160) NOT NULL,
            SP_ACTIVE TINYINT(1) NOT NULL,
            SP_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_SURPRISE_POOL_AGENCY (SP_AGENCY),
            INDEX IDX_SURPRISE_POOL_ACTIVE (SP_AGENCY, SP_ACTIVE),
            PRIMARY KEY(SP_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `surprise_pool_item` (
            SI_ID VARCHAR(16) NOT NULL,
            SI_POOL VARCHAR(16) NOT NULL,
            SI_LABEL VARCHAR(160) NOT NULL,
            SI_REWARD_TYPE VARCHAR(40) NOT NULL,
            SI_REWARD_VALUE INT NOT NULL,
            SI_WEIGHT INT DEFAULT 1 NOT NULL,
            SI_ACTIVE TINYINT(1) NOT NULL,
            INDEX IDX_SURPRISE_POOL_ITEM_POOL (SI_POOL),
            PRIMARY KEY(SI_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `traveler_wallet` ADD CONSTRAINT FK_TRAVELER_WALLET_USER FOREIGN KEY (WA_USER) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `wallet_ledger` ADD CONSTRAINT FK_WALLET_LEDGER_WALLET FOREIGN KEY (WL_WALLET) REFERENCES `traveler_wallet` (WA_ID)');

        $this->addSql('ALTER TABLE `wallet_topup` ADD CONSTRAINT FK_WALLET_TOPUP_WALLET FOREIGN KEY (WT_WALLET) REFERENCES `traveler_wallet` (WA_ID)');
        $this->addSql('ALTER TABLE `wallet_topup` ADD CONSTRAINT FK_WALLET_TOPUP_USER FOREIGN KEY (WT_USER) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `traveler_pass_product` ADD CONSTRAINT FK_TRAVELER_PASS_PRODUCT_AGENCY FOREIGN KEY (PP_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `traveler_pass` ADD CONSTRAINT FK_TRAVELER_PASS_USER FOREIGN KEY (TP_USER) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `traveler_pass` ADD CONSTRAINT FK_TRAVELER_PASS_PRODUCT FOREIGN KEY (TP_PRODUCT) REFERENCES `traveler_pass_product` (PP_ID)');
        $this->addSql('ALTER TABLE `traveler_pass` ADD CONSTRAINT FK_TRAVELER_PASS_AGENCY FOREIGN KEY (TP_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `traveler_preorder` ADD CONSTRAINT FK_TRAVELER_PREORDER_USER FOREIGN KEY (PO_USER) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `traveler_preorder` ADD CONSTRAINT FK_TRAVELER_PREORDER_AGENCY FOREIGN KEY (PO_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `traveler_preorder` ADD CONSTRAINT FK_TRAVELER_PREORDER_OFFER FOREIGN KEY (PO_OFFER) REFERENCES `agency_offer` (AO_ID)');
        $this->addSql('ALTER TABLE `traveler_preorder` ADD CONSTRAINT FK_TRAVELER_PREORDER_BOOKING FOREIGN KEY (PO_BOOKING) REFERENCES `agency_booking` (AB_ID)');

        $this->addSql('ALTER TABLE `accounting_journal` ADD CONSTRAINT FK_ACCOUNTING_JOURNAL_AGENCY FOREIGN KEY (AJ_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `accounting_daily_close` ADD CONSTRAINT FK_ACCOUNTING_DAILY_CLOSE_AGENCY FOREIGN KEY (AC_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `accounting_daily_close` ADD CONSTRAINT FK_ACCOUNTING_DAILY_CLOSE_CLOSED_BY FOREIGN KEY (AC_CLOSED_BY) REFERENCES `user` (US_ID)');

        $this->addSql('ALTER TABLE `loyalty_account` ADD CONSTRAINT FK_LOYALTY_ACCOUNT_AGENCY FOREIGN KEY (LA_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `loyalty_point_ledger` ADD CONSTRAINT FK_LOYALTY_POINT_LEDGER_ACCOUNT FOREIGN KEY (LP_ACCOUNT) REFERENCES `loyalty_account` (LA_ID)');
        $this->addSql('ALTER TABLE `loyalty_point_ledger` ADD CONSTRAINT FK_LOYALTY_POINT_LEDGER_TICKET FOREIGN KEY (LP_TICKET) REFERENCES `agency_ticket` (AK_ID)');

        $this->addSql('ALTER TABLE `surprise_pool` ADD CONSTRAINT FK_SURPRISE_POOL_AGENCY FOREIGN KEY (SP_AGENCY) REFERENCES `agency` (AG_ID)');

        $this->addSql('ALTER TABLE `surprise_pool_item` ADD CONSTRAINT FK_SURPRISE_POOL_ITEM_POOL FOREIGN KEY (SI_POOL) REFERENCES `surprise_pool` (SP_ID)');

        $this->addSql('ALTER TABLE `loyalty_rule` ADD LR_SURPRISE_POOL VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE `loyalty_rule` ADD LR_POINTS_EARN INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `loyalty_rule` ADD CONSTRAINT FK_LOYALTY_RULE_SURPRISE_POOL FOREIGN KEY (LR_SURPRISE_POOL) REFERENCES `surprise_pool` (SP_ID)');
        $this->addSql('CREATE INDEX IDX_LOYALTY_RULE_SURPRISE_POOL ON `loyalty_rule` (LR_SURPRISE_POOL)');

        $this->addSql('ALTER TABLE `agency_offer` ADD AO_PREORDER_HOLD_HOURS INT DEFAULT 72 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_PREORDER_HOLD_HOURS');

        $this->addSql('ALTER TABLE `loyalty_rule` DROP FOREIGN KEY FK_LOYALTY_RULE_SURPRISE_POOL');
        $this->addSql('DROP INDEX IDX_LOYALTY_RULE_SURPRISE_POOL ON `loyalty_rule`');
        $this->addSql('ALTER TABLE `loyalty_rule` DROP LR_SURPRISE_POOL');
        $this->addSql('ALTER TABLE `loyalty_rule` DROP LR_POINTS_EARN');

        $this->addSql('ALTER TABLE `surprise_pool_item` DROP FOREIGN KEY FK_SURPRISE_POOL_ITEM_POOL');
        $this->addSql('ALTER TABLE `surprise_pool` DROP FOREIGN KEY FK_SURPRISE_POOL_AGENCY');
        $this->addSql('ALTER TABLE `loyalty_point_ledger` DROP FOREIGN KEY FK_LOYALTY_POINT_LEDGER_TICKET');
        $this->addSql('ALTER TABLE `loyalty_point_ledger` DROP FOREIGN KEY FK_LOYALTY_POINT_LEDGER_ACCOUNT');
        $this->addSql('ALTER TABLE `loyalty_account` DROP FOREIGN KEY FK_LOYALTY_ACCOUNT_AGENCY');
        $this->addSql('ALTER TABLE `accounting_daily_close` DROP FOREIGN KEY FK_ACCOUNTING_DAILY_CLOSE_CLOSED_BY');
        $this->addSql('ALTER TABLE `accounting_daily_close` DROP FOREIGN KEY FK_ACCOUNTING_DAILY_CLOSE_AGENCY');
        $this->addSql('ALTER TABLE `accounting_journal` DROP FOREIGN KEY FK_ACCOUNTING_JOURNAL_AGENCY');
        $this->addSql('ALTER TABLE `traveler_preorder` DROP FOREIGN KEY FK_TRAVELER_PREORDER_BOOKING');
        $this->addSql('ALTER TABLE `traveler_preorder` DROP FOREIGN KEY FK_TRAVELER_PREORDER_OFFER');
        $this->addSql('ALTER TABLE `traveler_preorder` DROP FOREIGN KEY FK_TRAVELER_PREORDER_AGENCY');
        $this->addSql('ALTER TABLE `traveler_preorder` DROP FOREIGN KEY FK_TRAVELER_PREORDER_USER');
        $this->addSql('ALTER TABLE `traveler_pass` DROP FOREIGN KEY FK_TRAVELER_PASS_AGENCY');
        $this->addSql('ALTER TABLE `traveler_pass` DROP FOREIGN KEY FK_TRAVELER_PASS_PRODUCT');
        $this->addSql('ALTER TABLE `traveler_pass` DROP FOREIGN KEY FK_TRAVELER_PASS_USER');
        $this->addSql('ALTER TABLE `traveler_pass_product` DROP FOREIGN KEY FK_TRAVELER_PASS_PRODUCT_AGENCY');
        $this->addSql('ALTER TABLE `wallet_topup` DROP FOREIGN KEY FK_WALLET_TOPUP_USER');
        $this->addSql('ALTER TABLE `wallet_topup` DROP FOREIGN KEY FK_WALLET_TOPUP_WALLET');
        $this->addSql('ALTER TABLE `wallet_ledger` DROP FOREIGN KEY FK_WALLET_LEDGER_WALLET');
        $this->addSql('ALTER TABLE `traveler_wallet` DROP FOREIGN KEY FK_TRAVELER_WALLET_USER');

        $this->addSql('DROP TABLE `surprise_pool_item`');
        $this->addSql('DROP TABLE `surprise_pool`');
        $this->addSql('DROP TABLE `loyalty_point_ledger`');
        $this->addSql('DROP TABLE `loyalty_account`');
        $this->addSql('DROP TABLE `accounting_daily_close`');
        $this->addSql('DROP TABLE `accounting_journal`');
        $this->addSql('DROP TABLE `traveler_preorder`');
        $this->addSql('DROP TABLE `traveler_pass`');
        $this->addSql('DROP TABLE `traveler_pass_product`');
        $this->addSql('DROP TABLE `wallet_topup`');
        $this->addSql('DROP TABLE `wallet_ledger`');
        $this->addSql('DROP TABLE `traveler_wallet`');
    }
}
