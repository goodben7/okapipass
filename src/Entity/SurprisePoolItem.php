<?php

namespace App\Entity;

use App\Doctrine\IdGenerator;
use App\Model\RessourceInterface;
use App\Repository\SurprisePoolItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SurprisePoolItemRepository::class)]
#[ORM\Table(name: '`surprise_pool_item`')]
class SurprisePoolItem implements RessourceInterface
{
    public const string ID_PREFIX = 'SI';

    public const string REWARD_PERCENT_OFF = 'percent_off';
    public const string REWARD_FIXED_OFF = 'fixed_off';
    public const string REWARD_FREE_SEAT = 'free_seat';
    public const string REWARD_POINTS = 'points';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'SI_ID', length: 16)]
    #[Groups(['surprise_pool_item:get', 'surprise_pool:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'SI_POOL', nullable: false, referencedColumnName: 'SP_ID')]
    #[Groups(['surprise_pool_item:get'])]
    private ?SurprisePool $pool = null;

    #[ORM\Column(name: 'SI_LABEL', length: 160)]
    #[Groups(['surprise_pool_item:get', 'surprise_pool:get'])]
    private ?string $label = null;

    #[ORM\Column(name: 'SI_REWARD_TYPE', length: 40)]
    #[Assert\Choice(callback: [self::class, 'getRewardTypesAsList'])]
    #[Groups(['surprise_pool_item:get', 'surprise_pool:get'])]
    private string $rewardType = self::REWARD_PERCENT_OFF;

    #[ORM\Column(name: 'SI_REWARD_VALUE')]
    #[Groups(['surprise_pool_item:get', 'surprise_pool:get'])]
    private int $rewardValue = 0;

    #[ORM\Column(name: 'SI_WEIGHT', options: ['default' => 1])]
    #[Groups(['surprise_pool_item:get'])]
    private int $weight = 1;

    #[ORM\Column(name: 'SI_ACTIVE')]
    #[Groups(['surprise_pool_item:get'])]
    private bool $active = true;

    /** @return list<string> */
    public static function getRewardTypesAsList(): array
    {
        return [
            self::REWARD_PERCENT_OFF,
            self::REWARD_FIXED_OFF,
            self::REWARD_FREE_SEAT,
            self::REWARD_POINTS,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPool(): ?SurprisePool
    {
        return $this->pool;
    }

    public function setPool(?SurprisePool $pool): static
    {
        $this->pool = $pool;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getRewardType(): string
    {
        return $this->rewardType;
    }

    public function setRewardType(string $rewardType): static
    {
        $this->rewardType = $rewardType;

        return $this;
    }

    public function getRewardValue(): int
    {
        return $this->rewardValue;
    }

    public function setRewardValue(int $rewardValue): static
    {
        $this->rewardValue = $rewardValue;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
