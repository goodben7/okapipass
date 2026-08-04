<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Repository\DeclarationLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DeclarationLineRepository::class)]
#[ORM\Table(name: '`declaration_line`')]
class DeclarationLine
{
    public const string ID_PREFIX = 'DL';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'DL_ID', length: 16)]
    #[Groups(['pass_declaration:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'DL_DECLARATION', nullable: false, referencedColumnName: 'PD_ID')]
    private ?PassDeclaration $declaration = null;

    #[ORM\Column(name: 'DL_REFERENCE_BILLET', length: 40)]
    #[Groups(['pass_declaration:get'])]
    private ?string $referenceBillet = null;

    #[ORM\Column(name: 'DL_DATE', type: Types::DATE_IMMUTABLE)]
    #[Groups(['pass_declaration:get'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(name: 'DL_PASSENGER_NAME', length: 120)]
    #[Groups(['pass_declaration:get'])]
    private ?string $passengerName = null;

    #[ORM\Column(name: 'DL_PASSENGER_ID', length: 60)]
    #[Groups(['pass_declaration:get'])]
    private ?string $passengerId = null;

    #[ORM\Column(name: 'DL_ORIGIN', length: 120)]
    #[Groups(['pass_declaration:get'])]
    private ?string $origin = null;

    #[ORM\Column(name: 'DL_DESTINATION', length: 120)]
    #[Groups(['pass_declaration:get'])]
    private ?string $destination = null;

    #[ORM\Column(name: 'DL_TICKET_PRICE')]
    #[Groups(['pass_declaration:get'])]
    private int $ticketPrice = 0;

    #[ORM\Column(name: 'DL_CURRENCY', length: 3)]
    #[Groups(['pass_declaration:get'])]
    private string $currency = Agency::DEFAULT_CURRENCY;

    #[ORM\Column(name: 'DL_PASS_PRICE')]
    #[Groups(['pass_declaration:get'])]
    private int $passPrice = 0;

    #[ORM\Column(name: 'DL_OKAPI_PASS_REF', length: 40, nullable: true)]
    #[Groups(['pass_declaration:get'])]
    private ?string $okapiPassRef = null;

    #[ORM\Column(name: 'DL_HAS_EXISTING_PASS')]
    #[Groups(['pass_declaration:get'])]
    private bool $hasExistingPass = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDeclaration(): ?PassDeclaration
    {
        return $this->declaration;
    }

    public function setDeclaration(?PassDeclaration $declaration): static
    {
        $this->declaration = $declaration;

        return $this;
    }

    public function getReferenceBillet(): ?string
    {
        return $this->referenceBillet;
    }

    public function setReferenceBillet(string $referenceBillet): static
    {
        $this->referenceBillet = $referenceBillet;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getPassengerName(): ?string
    {
        return $this->passengerName;
    }

    public function setPassengerName(string $passengerName): static
    {
        $this->passengerName = $passengerName;

        return $this;
    }

    public function getPassengerId(): ?string
    {
        return $this->passengerId;
    }

    public function setPassengerId(string $passengerId): static
    {
        $this->passengerId = $passengerId;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getTicketPrice(): int
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(int $ticketPrice): static
    {
        $this->ticketPrice = $ticketPrice;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPassPrice(): int
    {
        return $this->passPrice;
    }

    public function setPassPrice(int $passPrice): static
    {
        $this->passPrice = $passPrice;

        return $this;
    }

    public function getOkapiPassRef(): ?string
    {
        return $this->okapiPassRef;
    }

    public function setOkapiPassRef(?string $okapiPassRef): static
    {
        $ref = null !== $okapiPassRef ? strtoupper(trim($okapiPassRef)) : null;
        $this->okapiPassRef = '' === $ref ? null : $ref;

        return $this;
    }

    public function hasExistingPass(): bool
    {
        return $this->hasExistingPass;
    }

    public function setHasExistingPass(bool $hasExistingPass): static
    {
        $this->hasExistingPass = $hasExistingPass;

        return $this;
    }
}
