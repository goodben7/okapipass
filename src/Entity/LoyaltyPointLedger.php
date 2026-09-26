<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\LoyaltyPointLedgerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoyaltyPointLedgerRepository::class)]
#[ORM\Table(name: '`loyalty_point_ledger`')]
#[ORM\HasLifecycleCallbacks]
class LoyaltyPointLedger implements RessourceInterface
{
    public const string ID_PREFIX = 'LP';

    public const string REASON_EARN_TRIP = 'EARN_TRIP';
    public const string REASON_REDEEM = 'REDEEM';
    public const string REASON_SURPRISE = 'SURPRISE';
    public const string REASON_ADJUST = 'ADJUST';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'LP_ID', length: 16)]
    #[Groups(['loyalty_point_ledger:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'LP_ACCOUNT', nullable: false, referencedColumnName: 'LA_ID')]
    #[Groups(['loyalty_point_ledger:get'])]
    private ?LoyaltyAccount $account = null;

    #[ORM\Column(name: 'LP_DELTA')]
    #[Groups(['loyalty_point_ledger:get'])]
    private int $delta = 0;

    #[ORM\Column(name: 'LP_BALANCE_AFTER')]
    #[Groups(['loyalty_point_ledger:get'])]
    private int $balanceAfter = 0;

    #[ORM\Column(name: 'LP_REASON', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getReasonsAsList'])]
    #[Groups(['loyalty_point_ledger:get'])]
    private string $reason = self::REASON_EARN_TRIP;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'LP_TICKET', nullable: true, referencedColumnName: 'AK_ID')]
    #[Groups(['loyalty_point_ledger:get'])]
    private ?AgencyTicket $ticket = null;

    #[ORM\Column(name: 'LP_LABEL', length: 160, nullable: true)]
    #[Groups(['loyalty_point_ledger:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'LP_CREATED_AT')]
    #[Groups(['loyalty_point_ledger:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /** @return list<string> */
    public static function getReasonsAsList(): array
    {
        return [
            self::REASON_EARN_TRIP,
            self::REASON_REDEEM,
            self::REASON_SURPRISE,
            self::REASON_ADJUST,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAccount(): ?LoyaltyAccount
    {
        return $this->account;
    }

    public function setAccount(?LoyaltyAccount $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getDelta(): int
    {
        return $this->delta;
    }

    public function setDelta(int $delta): static
    {
        $this->delta = $delta;

        return $this;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getTicket(): ?AgencyTicket
    {
        return $this->ticket;
    }

    public function setTicket(?AgencyTicket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable('now');
    }
}
