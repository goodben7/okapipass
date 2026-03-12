<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312150821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment ADD PA_PROVIDER_TRANSACTION_ID VARCHAR(120) DEFAULT NULL, ADD PA_PROVIDER VARCHAR(30) DEFAULT NULL, ADD PA_PROVIDER_RESPONSE JSON DEFAULT NULL, ADD PA_PROVIDER_WEBHOOK JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `payment` DROP PA_PROVIDER_TRANSACTION_ID, DROP PA_PROVIDER, DROP PA_PROVIDER_RESPONSE, DROP PA_PROVIDER_WEBHOOK');
    }
}
