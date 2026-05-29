<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260529081803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `notification` (NF_ID VARCHAR(16) NOT NULL, NF_TYPE VARCHAR(15) NOT NULL, NF_SUBJECT VARCHAR(255) DEFAULT NULL, NF_BODY LONGTEXT NOT NULL, NF_TITLE VARCHAR(255) DEFAULT NULL, NF_DATA LONGTEXT DEFAULT NULL, NF_TEMPLATE VARCHAR(255) DEFAULT NULL, NF_TEMPLATE_CONTEXT JSON DEFAULT NULL, NF_IS_READ TINYINT NOT NULL, NF_SENT_VIA VARCHAR(30) NOT NULL, NF_TARGET VARCHAR(255) NOT NULL, NF_TARGET_TYPE VARCHAR(30) NOT NULL, NF_READ_AT DATETIME DEFAULT NULL, NF_CREATED_AT DATETIME NOT NULL, PRIMARY KEY (NF_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `ticket_verification` (TV_ID VARCHAR(16) NOT NULL, TV_VERIFIED_AT DATETIME NOT NULL, TV_COMMENT LONGTEXT DEFAULT NULL, TV_TICKET VARCHAR(16) NOT NULL, TV_VERIFIER VARCHAR(16) NOT NULL, TV_CHECKPOINT VARCHAR(16) NOT NULL, INDEX IDX_9A2739A059F9AC4D (TV_TICKET), INDEX IDX_9A2739A0E2816E8F (TV_VERIFIER), INDEX IDX_9A2739A019524F9C (TV_CHECKPOINT), PRIMARY KEY (TV_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `ticket_verification` ADD CONSTRAINT FK_9A2739A059F9AC4D FOREIGN KEY (TV_TICKET) REFERENCES `ticket` (TI_ID)');
        $this->addSql('ALTER TABLE `ticket_verification` ADD CONSTRAINT FK_9A2739A0E2816E8F FOREIGN KEY (TV_VERIFIER) REFERENCES `user` (US_ID)');
        $this->addSql('ALTER TABLE `ticket_verification` ADD CONSTRAINT FK_9A2739A019524F9C FOREIGN KEY (TV_CHECKPOINT) REFERENCES `checkpoint` (CP_ID)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `ticket_verification` DROP FOREIGN KEY FK_9A2739A059F9AC4D');
        $this->addSql('ALTER TABLE `ticket_verification` DROP FOREIGN KEY FK_9A2739A0E2816E8F');
        $this->addSql('ALTER TABLE `ticket_verification` DROP FOREIGN KEY FK_9A2739A019524F9C');
        $this->addSql('DROP TABLE `notification`');
        $this->addSql('DROP TABLE `ticket_verification`');
    }
}
