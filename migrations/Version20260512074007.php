<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512074007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `province` (PV_ID VARCHAR(16) NOT NULL, PV_LABEL VARCHAR(120) NOT NULL, PV_CODE VARCHAR(15) NOT NULL, PV_ACTIVE TINYINT NOT NULL, PV_CREATED_AT DATETIME NOT NULL, PV_UPDATED_AT DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_PROVINCE_CODE (PV_CODE), PRIMARY KEY (PV_ID)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE `province`');
    }
}
