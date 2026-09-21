<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ONT FPT validation workflow: validated/rejected timestamps + rejection reason';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `pass_declaration` ADD PD_VALIDATED_AT DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `pass_declaration` ADD PD_REJECTED_AT DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `pass_declaration` ADD PD_REJECTION_REASON VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `pass_declaration` DROP PD_REJECTION_REASON');
        $this->addSql('ALTER TABLE `pass_declaration` DROP PD_REJECTED_AT');
        $this->addSql('ALTER TABLE `pass_declaration` DROP PD_VALIDATED_AT');
    }
}
