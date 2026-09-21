<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Monthly FPT declarations: period month on pass_declaration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `pass_declaration` ADD PD_PERIOD_MONTH VARCHAR(7) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PASS_DECL_AGENCY_PERIOD ON `pass_declaration` (PD_AGENCY, PD_PERIOD_MONTH)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_PASS_DECL_AGENCY_PERIOD ON `pass_declaration`');
        $this->addSql('ALTER TABLE `pass_declaration` DROP PD_PERIOD_MONTH');
    }
}
