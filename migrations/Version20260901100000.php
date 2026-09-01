<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store payer phone on agency_payment for third-party ticket purchases';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_payment` ADD AP_PAYER_PHONE VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_payment` DROP AP_PAYER_PHONE');
    }
}
