<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512082042 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE checkpoint ADD CP_PROVINCE VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE checkpoint ADD CONSTRAINT FK_F00F7BED91951C3 FOREIGN KEY (CP_PROVINCE) REFERENCES `province` (PV_ID)');
        $this->addSql('CREATE INDEX IDX_F00F7BED91951C3 ON checkpoint (CP_PROVINCE)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `checkpoint` DROP FOREIGN KEY FK_F00F7BED91951C3');
        $this->addSql('DROP INDEX IDX_F00F7BED91951C3 ON `checkpoint`');
        $this->addSql('ALTER TABLE `checkpoint` DROP CP_PROVINCE');
    }
}
