<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Repository\AgencyTicketSequenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgencyTicketSequenceRepository::class)]
#[ORM\Table(name: '`agency_ticket_sequence`')]
#[ORM\UniqueConstraint(name: 'UNIQ_AGENCY_TICKET_SEQ', fields: ['agency', 'year'])]
class AgencyTicketSequence
{
    public const string ID_PREFIX = 'AS';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'AS_ID', length: 16)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AS_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'AS_YEAR')]
    private int $year = 0;

    #[ORM\Column(name: 'AS_LAST_NUMBER')]
    private int $lastNumber = 0;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAgency(): ?Agency
    {
        return $this->agency;
    }

    public function setAgency(Agency $agency): static
    {
        $this->agency = $agency;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getLastNumber(): int
    {
        return $this->lastNumber;
    }

    public function setLastNumber(int $lastNumber): static
    {
        $this->lastNumber = $lastNumber;

        return $this;
    }
}
