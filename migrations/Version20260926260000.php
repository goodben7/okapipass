<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926260000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vague 8 bus scolaire: offer serviceType, school contracts/students/attendance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `agency_offer` ADD AO_SERVICE_TYPE VARCHAR(16) DEFAULT 'INTERCITY' NOT NULL");

        $this->addSql('CREATE TABLE `school_contract` (
            SK_ID VARCHAR(16) NOT NULL,
            SK_AGENCY VARCHAR(16) NOT NULL,
            SK_SCHOOL_NAME VARCHAR(160) NOT NULL,
            SK_SCHOOL_PHONE VARCHAR(20) DEFAULT NULL,
            SK_SCHOOL_ADDRESS VARCHAR(255) DEFAULT NULL,
            SK_OFFER VARCHAR(16) NOT NULL,
            SK_TRANSPORT VARCHAR(16) DEFAULT NULL,
            SK_START_DATE DATE NOT NULL,
            SK_END_DATE DATE NOT NULL,
            SK_STATUS VARCHAR(16) NOT NULL,
            SK_MONTHLY_FEE INT NOT NULL,
            SK_CURRENCY VARCHAR(3) NOT NULL,
            SK_STOPS JSON DEFAULT NULL,
            SK_NOTES LONGTEXT DEFAULT NULL,
            SK_CREATED_AT DATETIME NOT NULL,
            SK_UPDATED_AT DATETIME DEFAULT NULL,
            INDEX IDX_SCHOOL_CONTRACT_AGENCY (SK_AGENCY),
            INDEX IDX_SCHOOL_CONTRACT_OFFER (SK_OFFER),
            INDEX IDX_SCHOOL_CONTRACT_TRANSPORT (SK_TRANSPORT),
            INDEX IDX_SCHOOL_CONTRACT_STATUS (SK_STATUS),
            PRIMARY KEY(SK_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `school_student` (
            SU_ID VARCHAR(16) NOT NULL,
            SU_AGENCY VARCHAR(16) NOT NULL,
            SU_CONTRACT VARCHAR(16) NOT NULL,
            SU_FULL_NAME VARCHAR(160) NOT NULL,
            SU_PHONE VARCHAR(20) DEFAULT NULL,
            SU_GRADE VARCHAR(40) DEFAULT NULL,
            SU_PICKUP_STOP_CODE VARCHAR(40) DEFAULT NULL,
            SU_DROPOFF_STOP_CODE VARCHAR(40) DEFAULT NULL,
            SU_ACTIVE TINYINT(1) NOT NULL,
            SU_EXTERNAL_REF VARCHAR(64) DEFAULT NULL,
            SU_CREATED_AT DATETIME NOT NULL,
            INDEX IDX_SCHOOL_STUDENT_AGENCY (SU_AGENCY),
            INDEX IDX_SCHOOL_STUDENT_CONTRACT (SU_CONTRACT),
            INDEX IDX_SCHOOL_STUDENT_ACTIVE (SU_ACTIVE),
            PRIMARY KEY(SU_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE `school_attendance` (
            SA_ID VARCHAR(16) NOT NULL,
            SA_AGENCY VARCHAR(16) NOT NULL,
            SA_CONTRACT VARCHAR(16) NOT NULL,
            SA_STUDENT VARCHAR(16) NOT NULL,
            SA_ATTENDANCE_DATE DATE NOT NULL,
            SA_STATUS VARCHAR(16) NOT NULL,
            SA_RECORDED_AT DATETIME NOT NULL,
            SA_RECORDED_BY VARCHAR(16) DEFAULT NULL,
            UNIQUE INDEX UNIQ_SCHOOL_ATTENDANCE_STUDENT_DATE (SA_STUDENT, SA_ATTENDANCE_DATE),
            INDEX IDX_SCHOOL_ATTENDANCE_AGENCY (SA_AGENCY),
            INDEX IDX_SCHOOL_ATTENDANCE_CONTRACT (SA_CONTRACT),
            INDEX IDX_SCHOOL_ATTENDANCE_STUDENT (SA_STUDENT),
            INDEX IDX_SCHOOL_ATTENDANCE_RECORDED_BY (SA_RECORDED_BY),
            PRIMARY KEY(SA_ID)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE `school_contract` ADD CONSTRAINT FK_SCHOOL_CONTRACT_AGENCY FOREIGN KEY (SK_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `school_contract` ADD CONSTRAINT FK_SCHOOL_CONTRACT_OFFER FOREIGN KEY (SK_OFFER) REFERENCES `agency_offer` (AO_ID)');
        $this->addSql('ALTER TABLE `school_contract` ADD CONSTRAINT FK_SCHOOL_CONTRACT_TRANSPORT FOREIGN KEY (SK_TRANSPORT) REFERENCES `agency_transport` (AT_ID)');
        $this->addSql('ALTER TABLE `school_student` ADD CONSTRAINT FK_SCHOOL_STUDENT_AGENCY FOREIGN KEY (SU_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `school_student` ADD CONSTRAINT FK_SCHOOL_STUDENT_CONTRACT FOREIGN KEY (SU_CONTRACT) REFERENCES `school_contract` (SK_ID)');
        $this->addSql('ALTER TABLE `school_attendance` ADD CONSTRAINT FK_SCHOOL_ATTENDANCE_AGENCY FOREIGN KEY (SA_AGENCY) REFERENCES `agency` (AG_ID)');
        $this->addSql('ALTER TABLE `school_attendance` ADD CONSTRAINT FK_SCHOOL_ATTENDANCE_CONTRACT FOREIGN KEY (SA_CONTRACT) REFERENCES `school_contract` (SK_ID)');
        $this->addSql('ALTER TABLE `school_attendance` ADD CONSTRAINT FK_SCHOOL_ATTENDANCE_STUDENT FOREIGN KEY (SA_STUDENT) REFERENCES `school_student` (SU_ID)');
        $this->addSql('ALTER TABLE `school_attendance` ADD CONSTRAINT FK_SCHOOL_ATTENDANCE_RECORDED_BY FOREIGN KEY (SA_RECORDED_BY) REFERENCES `user` (US_ID)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `school_attendance` DROP FOREIGN KEY FK_SCHOOL_ATTENDANCE_RECORDED_BY');
        $this->addSql('ALTER TABLE `school_attendance` DROP FOREIGN KEY FK_SCHOOL_ATTENDANCE_STUDENT');
        $this->addSql('ALTER TABLE `school_attendance` DROP FOREIGN KEY FK_SCHOOL_ATTENDANCE_CONTRACT');
        $this->addSql('ALTER TABLE `school_attendance` DROP FOREIGN KEY FK_SCHOOL_ATTENDANCE_AGENCY');
        $this->addSql('ALTER TABLE `school_student` DROP FOREIGN KEY FK_SCHOOL_STUDENT_CONTRACT');
        $this->addSql('ALTER TABLE `school_student` DROP FOREIGN KEY FK_SCHOOL_STUDENT_AGENCY');
        $this->addSql('ALTER TABLE `school_contract` DROP FOREIGN KEY FK_SCHOOL_CONTRACT_TRANSPORT');
        $this->addSql('ALTER TABLE `school_contract` DROP FOREIGN KEY FK_SCHOOL_CONTRACT_OFFER');
        $this->addSql('ALTER TABLE `school_contract` DROP FOREIGN KEY FK_SCHOOL_CONTRACT_AGENCY');
        $this->addSql('DROP TABLE `school_attendance`');
        $this->addSql('DROP TABLE `school_student`');
        $this->addSql('DROP TABLE `school_contract`');
        $this->addSql('ALTER TABLE `agency_offer` DROP AO_SERVICE_TYPE');
    }
}
