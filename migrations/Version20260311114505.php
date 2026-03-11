<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311114505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `gopass` (GP_ID VARCHAR(16) NOT NULL, GP_CODE VARCHAR(50) NOT NULL, GP_LABEL VARCHAR(120) NOT NULL, GP_TRANSPORT_TYPE VARCHAR(10) NOT NULL, GP_PRICE DOUBLE PRECISION NOT NULL, GP_CURRENCY VARCHAR(3) NOT NULL, GP_ACTIVE TINYINT NOT NULL, GP_CREATED_AT DATETIME NOT NULL, UNIQUE INDEX UNIQ_GOPASS_CODE (GP_CODE), PRIMARY KEY (GP_ID)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE `gopass`');
    }
}
