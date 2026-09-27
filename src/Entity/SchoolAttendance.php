<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Model\RessourceInterface;
use App\Repository\SchoolAttendanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SchoolAttendanceRepository::class)]
#[ORM\Table(name: '`school_attendance`')]
#[ORM\UniqueConstraint(name: 'UNIQ_SCHOOL_ATTENDANCE_STUDENT_DATE', fields: ['student', 'attendanceDate'])]
#[ORM\HasLifecycleCallbacks]
class SchoolAttendance implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'SA';

    public const string STATUS_PRESENT = 'PRESENT';
    public const string STATUS_ABSENT = 'ABSENT';
    public const string STATUS_BOARDED = 'BOARDED';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SA_ID', length: 16)]
    #[Groups(['school_attendance:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SA_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['school_attendance:get'])]
    private ?Agency $agency = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SA_CONTRACT', nullable: false, referencedColumnName: 'SK_ID')]
    #[Groups(['school_attendance:get'])]
    private ?SchoolContract $contract = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SA_STUDENT', nullable: false, referencedColumnName: 'SU_ID')]
    #[Groups(['school_attendance:get'])]
    private ?SchoolStudent $student = null;

    #[ORM\Column(name: 'SA_ATTENDANCE_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['school_attendance:get'])]
    private ?\DateTimeImmutable $attendanceDate = null;

    #[ORM\Column(name: 'SA_STATUS', length: 16)]
    #[Assert\Choice(callback: [self::class, 'getStatusesAsList'])]
    #[Groups(['school_attendance:get'])]
    private string $status = self::STATUS_PRESENT;

    #[ORM\Column(name: 'SA_RECORDED_AT')]
    #[Groups(['school_attendance:get'])]
    private ?\DateTimeImmutable $recordedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SA_RECORDED_BY', nullable: true, referencedColumnName: 'US_ID')]
    #[Groups(['school_attendance:get'])]
    private ?User $recordedBy = null;

    /** @return list<string> */
    public static function getStatusesAsList(): array
    {
        return [
            self::STATUS_PRESENT,
            self::STATUS_ABSENT,
            self::STATUS_BOARDED,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(?Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getContract(): ?SchoolContract
    {
        return $this->contract;
    }

    public function setContract(?SchoolContract $contract): static
    {
        $this->contract = $contract;

        return $this;
    }

    public function getStudent(): ?SchoolStudent
    {
        return $this->student;
    }

    public function setStudent(?SchoolStudent $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getAttendanceDate(): ?\DateTimeImmutable
    {
        return $this->attendanceDate;
    }

    public function setAttendanceDate(\DateTimeImmutable $attendanceDate): static
    {
        $this->attendanceDate = $attendanceDate;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRecordedAt(): ?\DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeImmutable $recordedAt): static
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?User $recordedBy): static
    {
        $this->recordedBy = $recordedBy;

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->recordedAt ??= new \DateTimeImmutable('now');
    }
}
