<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311110702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile CHANGE PR_PERSON_TYPE PR_PERSON_TYPE VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE US_PERSON_TYPE US_PERSON_TYPE VARCHAR(30) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `profile` CHANGE PR_PERSON_TYPE PR_PERSON_TYPE VARCHAR(6) NOT NULL');
        $this->addSql('ALTER TABLE `user` CHANGE US_PERSON_TYPE US_PERSON_TYPE VARCHAR(20) NOT NULL');
    }
}
