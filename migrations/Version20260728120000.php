<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 0 Agency portal — additive columns only (non-breaking).
 */
final class Version20260728120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add agency licenseNumber, defaultCurrency, updatedAt for Agency portal (P0)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency` ADD AG_LICENSE_NUMBER VARCHAR(50) DEFAULT NULL');
        $this->addSql("ALTER TABLE `agency` ADD AG_DEFAULT_CURRENCY VARCHAR(3) DEFAULT 'CDF' NOT NULL");
        $this->addSql('ALTER TABLE `agency` ADD AG_UPDATED_AT DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency` DROP AG_LICENSE_NUMBER');
        $this->addSql('ALTER TABLE `agency` DROP AG_DEFAULT_CURRENCY');
        $this->addSql('ALTER TABLE `agency` DROP AG_UPDATED_AT');
    }
}
