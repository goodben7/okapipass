<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926320000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 11 Phase D / P2: school_invoice, ticket lastBoardedAt, traveler_pass lastConsumedAt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `school_invoice` (
            IV_ID VARCHAR(16) NOT NULL,
            IV_AGENCY VARCHAR(16) NOT NULL,
            IV_CONTRACT VARCHAR(16) NOT NULL,
            IV_PERIOD_YM VARCHAR(7) NOT NULL,
            IV_AMOUNT INT NOT NULL,
            IV_CURRENCY VARCHAR(3) NOT NULL,
            IV_STATUS VARCHAR(16) NOT NULL,
            IV_ISSUED_AT DATETIME DEFAULT NULL,
            IV_PAID_AT DATETIME DEFAULT NULL,
            IV_NOTES LONGTEXT DEFAULT NULL,
            IV_CREATED_AT DATETIME NOT NULL,
            IV_UPDATED_AT DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_SCHOOL_INVOICE_CONTRACT_PERIOD (IV_CONTRACT, IV_PERIOD_YM),
            INDEX IDX_SCHOOL_INVOICE_AGENCY (IV_AGENCY),
            INDEX IDX_SCHOOL_INVOICE_CONTRACT (IV_CONTRACT),
            INDEX IDX_SCHOOL_INVOICE_STATUS (IV_STATUS),
            PRIMARY KEY(IV_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `school_invoice` ADD CONSTRAINT FK_SCHOOL_INVOICE_AGENCY FOREIGN KEY (IV_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `school_invoice` ADD CONSTRAINT FK_SCHOOL_INVOICE_CONTRACT FOREIGN KEY (IV_CONTRACT) REFERENCES `school_contract` (SK_ID)');

        $this->addSql('ALTER TABLE `agency_ticket` ADD AK_LAST_BOARDED_AT DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `traveler_pass` ADD TP_LAST_CONSUMED_AT DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `traveler_pass` DROP TP_LAST_CONSUMED_AT');
        $this->addSql('ALTER TABLE `agency_ticket` DROP AK_LAST_BOARDED_AT');
        $this->addSql('ALTER TABLE `school_invoice` DROP FOREIGN KEY FK_SCHOOL_INVOICE_CONTRACT');
        $this->addSql('ALTER TABLE `school_invoice` DROP FOREIGN KEY FK_SCHOOL_INVOICE_AGENCY');
        $this->addSql('DROP TABLE `school_invoice`');
    }
}
