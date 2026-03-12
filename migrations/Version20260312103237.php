<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312103237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hotel ADD HO_CITY VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE hotel ADD CONSTRAINT FK_3535ED9944F345F FOREIGN KEY (HO_CITY) REFERENCES `checkpoint` (CP_ID)');
        $this->addSql('CREATE INDEX IDX_3535ED9944F345F ON hotel (HO_CITY)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `hotel` DROP FOREIGN KEY FK_3535ED9944F345F');
        $this->addSql('DROP INDEX IDX_3535ED9944F345F ON `hotel`');
        $this->addSql('ALTER TABLE `hotel` DROP HO_CITY');
    }
}
