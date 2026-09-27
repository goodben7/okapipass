<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 9 Phase B / URBAN: offer seatMode + traveler_pass_product.serviceType';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `agency_offer` ADD AO_SEAT_MODE VARCHAR(20) DEFAULT 'ASSIGNED_SEAT' NOT NULL");
        $this->addSql("UPDATE `agency_offer` SET AO_SEAT_MODE = 'NONE' WHERE AO_SERVICE_TYPE = 'SCHOOL'");
        $this->addSql("UPDATE `agency_offer` SET AO_SEAT_MODE = 'CAPACITY_ONLY' WHERE AO_SERVICE_TYPE = 'URBAN'");

        $this->addSql('ALTER TABLE `traveler_pass_product` ADD PP_SERVICE_TYPE VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `traveler_pass_product` DROP PP_SERVICE_TYPE');
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_SEAT_MODE');
    }
}
