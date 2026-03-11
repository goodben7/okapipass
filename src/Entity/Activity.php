<?php

namespace App\Entity;

use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ActivityRepository;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: ActivityRepository::class)]  
#[ORM\Table(name: '`activity`')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: 'is_granted("ROLE_ACTIVITY_LIST")'
        ),
        new Get(
            security: 'is_granted("ROLE_ACTIVITY_VIEW")'
        )
    ],
    normalizationContext: ['groups' => ['activity:view']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'user' => 'exact',
    'activity' => 'exact',
    'ressourceName' => 'exact',
    'ressourceIdentifier' => 'exact',
    'triggeredBy' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['date'])]
#[ApiFilter(DateFilter::class, properties: ['date'])]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'AC_ID')]
    #[Groups(['activity:view'])]
    private ?int $id = null;

    #[ORM\Column(name: 'AC_ACTIVITY', length: 20)]
    #[Groups(['activity:view'])]
    private ?string $activity = null;

    #[ORM\Column(name: 'AC_ACTIVITY_DESCRIPTION', length: 100, nullable: true)]
    #[Groups(['activity:view'])]
    private ?string $activityDescription = null;

    #[ORM\Column(name: 'AC_RESSOURCE_NAME', length: 50)]
    #[Groups(['activity:view'])]
    private ?string $ressourceName = null;

    #[ORM\Column(name: 'AC_RESSOURCE_IDENTIFIER', length: 16, nullable: true)]
    #[Groups(['activity:view'])]
    private ?string $ressourceIdentifier = null;

    #[ORM\Column(name: 'AC_USER', length: 16)]
    #[Groups(['activity:view'])]
    private ?string $user = null;

    #[ORM\Column(name: 'AC_DATE')]
    #[Groups(['activity:view'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'AC_TRIGGERED_BY', nullable: true, referencedColumnName:'US_ID')]
    #[Groups(['activity:view'])]
    private ?User $triggeredBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): ?string
    {
        return $this->activity;
    }

    public function setActivity(string $activity): static
    {
        $this->activity = $activity;

        return $this;
    }

    public function getActivityDescription(): ?string
    {
        return $this->activityDescription;
    }

    public function setActivityDescription(?string $activityDescription): static
    {
        $this->activityDescription = $activityDescription;

        return $this;
    }

    public function getRessourceName(): ?string
    {
        return $this->ressourceName;
    }

    public function setRessourceName(string $ressourceName): static
    {
        $this->ressourceName = $ressourceName;

        return $this;
    }

    public function getRessourceIdentifier(): ?string
    {
        return $this->ressourceIdentifier;
    }

    public function setRessourceIdentifier(?string $ressourceIdentifier): static
    {
        $this->ressourceIdentifier = $ressourceIdentifier;

        return $this;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function setUser(string $user): static
    {
        $this->user = $user;

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

    /**
     * Get the value of triggeredBy
     */ 
    public function getTriggeredBy(): User|null 
    {
        return $this->triggeredBy;
    }

    /**
     * Set the value of triggeredBy
     *
     * @return  self
     */ 
    public function setTriggeredBy(?User $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }
}
