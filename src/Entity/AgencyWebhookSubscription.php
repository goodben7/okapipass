<?php

namespace App\Entity;

use App\Security\AgencyPortalAccess;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\IdGenerator;
use App\Domain\Agency\AgencyScopedInterface;
use App\Dto\Agency\CreateAgencyWebhookDto;
use App\Dto\Agency\UpdateAgencyWebhookDto;
use App\Model\RessourceInterface;
use App\Repository\AgencyWebhookSubscriptionRepository;
use App\State\Agency\AgencyScopedItemProvider;
use App\State\Agency\CreateAgencyWebhookProcessor;
use App\State\Agency\DeleteAgencyWebhookProcessor;
use App\State\Agency\UpdateAgencyWebhookProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgencyWebhookSubscriptionRepository::class)]
#[ORM\Table(name: '`agency_webhook_subscription`')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'AgencyWebhookSubscription',
    normalizationContext: ['groups' => ['agency_webhook:get']],
    operations: [
        new GetCollection(
            uriTemplate: '/agency/webhooks',
            security: AgencyPortalAccess::EXPRESSION,
            provider: CollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/agency/webhooks/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
        ),
        new Post(
            uriTemplate: '/agency/webhooks',
            security: AgencyPortalAccess::EXPRESSION,
            input: CreateAgencyWebhookDto::class,
            processor: CreateAgencyWebhookProcessor::class,
            status: 201,
        ),
        new Patch(
            uriTemplate: '/agency/webhooks/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            input: UpdateAgencyWebhookDto::class,
            provider: AgencyScopedItemProvider::class,
            processor: UpdateAgencyWebhookProcessor::class,
        ),
        new Delete(
            uriTemplate: '/agency/webhooks/{id}',
            security: AgencyPortalAccess::EXPRESSION,
            provider: AgencyScopedItemProvider::class,
            processor: DeleteAgencyWebhookProcessor::class,
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact'])]
#[ApiFilter(BooleanFilter::class, properties: ['active'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
class AgencyWebhookSubscription implements RessourceInterface, AgencyScopedInterface
{
    public const string ID_PREFIX = 'WH';

    public const string EVENT_PAYMENT_PAID = 'payment.paid';
    public const string EVENT_TICKET_ISSUED = 'ticket.issued';
    public const string EVENT_TRIP_TRANSPORT_ASSIGNED = 'trip.transport_assigned';
    public const string EVENT_TRIP_TRANSPORT_UNASSIGNED = 'trip.transport_unassigned';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'WH_ID', length: 16)]
    #[Groups(['agency_webhook:get'])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'WH_AGENCY', nullable: false, referencedColumnName: 'AG_ID')]
    #[Groups(['agency_webhook:get'])]
    private ?Agency $agency = null;

    #[ORM\Column(name: 'WH_URL', length: 512)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Groups(['agency_webhook:get'])]
    private ?string $url = null;

    #[ORM\Column(name: 'WH_SECRET', length: 120)]
    #[Groups(['agency_webhook:get'])]
    private ?string $secret = null;

    /** @var list<string> */
    #[ORM\Column(name: 'WH_EVENTS', type: Types::JSON)]
    #[Groups(['agency_webhook:get'])]
    private array $events = [];

    #[ORM\Column(name: 'WH_ACTIVE')]
    #[Groups(['agency_webhook:get'])]
    private bool $active = true;

    #[ORM\Column(name: 'WH_CREATED_AT')]
    #[Groups(['agency_webhook:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @return list<string>
     */
    public static function getEventsAsList(): array
    {
        return [
            self::EVENT_PAYMENT_PAID,
            self::EVENT_TICKET_ISSUED,
            self::EVENT_TRIP_TRANSPORT_ASSIGNED,
            self::EVENT_TRIP_TRANSPORT_UNASSIGNED,
            self::EVENT_TRIP_TRANSPORT_ASSIGNED,
            self::EVENT_TRIP_TRANSPORT_UNASSIGNED,
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    /** @return list<string> */
    public function getEvents(): array
    {
        return $this->events;
    }

    /** @param list<string> $events */
    public function setEvents(array $events): static
    {
        $this->events = array_values($events);

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
