<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Issued OkapiPass registry for agency Pass validation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `issued_okapi_pass` (IP_ID VARCHAR(16) NOT NULL, IP_REFERENCE VARCHAR(40) NOT NULL, IP_HOLDER_NAME VARCHAR(120) NOT NULL, IP_STATUS VARCHAR(12) NOT NULL, IP_EXPIRES_AT DATETIME DEFAULT NULL, IP_CREATED_AT DATETIME NOT NULL, UNIQUE INDEX UNIQ_ISSUED_OKAPI_PASS_REF (IP_REFERENCE), PRIMARY KEY(IP_ID)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `issued_okapi_pass`');
    }
}
