<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint A — AgencyTransport + AgencyOffer tables.
 */
final class Version20260728130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create agency_transport and agency_offer for partner portal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `agency_transport` (AT_ID VARCHAR(16) NOT NULL, AT_LABEL VARCHAR(120) NOT NULL, AT_KIND VARCHAR(10) NOT NULL, AT_PLATE_NUMBER VARCHAR(30) NOT NULL, AT_CAPACITY INT NOT NULL, AT_STATUS VARCHAR(12) NOT NULL, AT_CREATED_AT DATETIME NOT NULL, AT_UPDATED_AT DATETIME DEFAULT NULL, AT_AGENCY VARCHAR(16) NOT NULL, INDEX IDX_AGENCY_TRANSPORT_AGENCY (AT_AGENCY), UNIQUE INDEX UNIQ_AGENCY_TRANSPORT_PLATE (AT_AGENCY, AT_PLATE_NUMBER), PRIMARY KEY(AT_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `agency_offer` (AO_ID VARCHAR(16) NOT NULL, AO_LABEL VARCHAR(160) NOT NULL, AO_ORIGIN VARCHAR(120) NOT NULL, AO_DESTINATION VARCHAR(120) NOT NULL, AO_TICKET_PRICE INT NOT NULL, AO_CURRENCY VARCHAR(3) NOT NULL, AO_DEPARTURE_TIME VARCHAR(5) NOT NULL, AO_DURATION_MINUTES INT NOT NULL, AO_ACTIVE TINYINT(1) NOT NULL, AO_CREATED_AT DATETIME NOT NULL, AO_UPDATED_AT DATETIME DEFAULT NULL, AO_AGENCY VARCHAR(16) NOT NULL, AO_TRANSPORT VARCHAR(16) NOT NULL, INDEX IDX_AGENCY_OFFER_AGENCY (AO_AGENCY), INDEX IDX_AGENCY_OFFER_TRANSPORT (AO_TRANSPORT), PRIMARY KEY(AO_ID)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `agency_transport` ADD CONSTRAINT FK_AGENCY_TRANSPORT_AGENCY FOREIGN KEY (AT_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_offer` ADD CONSTRAINT FK_AGENCY_OFFER_AGENCY FOREIGN KEY (AO_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `agency_offer` ADD CONSTRAINT FK_AGENCY_OFFER_TRANSPORT FOREIGN KEY (AO_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `agency_offer` DROP FOREIGN KEY FK_AGENCY_OFFER_AGENCY');
        $this->addSql('ALTER TABLE `agency_offer` DROP FOREIGN KEY FK_AGENCY_OFFER_TRANSPORT');
        $this->addSql('ALTER TABLE `agency_transport` DROP FOREIGN KEY FK_AGENCY_TRANSPORT_AGENCY');
        $this->addSql('DROP TABLE `agency_offer`');
        $this->addSql('DROP TABLE `agency_transport`');
    }
}
