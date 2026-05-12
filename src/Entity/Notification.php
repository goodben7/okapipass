<?php

namespace App\Entity;

use ApiPlatform\Metadata\Get;
use App\Doctrine\IdGenerator;
use App\Enum\NotificationType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Model\RessourceInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Provider\UserNotificationProvider;
use App\Repository\NotificationRepository;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\Serializer\Attribute\Groups;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: '`notification`')]
#[ApiResource(
    normalizationContext: ['groups' => 'notification:get'],
    operations: [
        new GetCollection(
            security: 'is_granted("ROLE_NOTIFICATION_LIST")',
            provider: CollectionProvider::class
        ),
        new GetCollection(
            uriTemplate: '/notifications/me',
            normalizationContext: ['groups' => 'notification:get'],
            security: 'is_granted("ROLE_USER")',
            provider: UserNotificationProvider::class 
        ), 
        new Get(
            security: 'is_granted("ROLE_NOTIFICATION_DETAILS")',
            provider: ItemProvider::class
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'type' => 'exact',
    'recipient' => 'exact',
    'recipientType' => 'exact',
    'isRead' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'readAt'])]
class Notification implements RessourceInterface
{
    public const string ID_PREFIX = "NF";

    public const string SENT_VIA_SYSTEM = 'system';
    public const string SENT_VIA_SMS = 'sms';
    public const string SENT_VIA_EMAIL = 'email';
    public const string SENT_VIA_GMAIL = 'gmail';
    public const string SENT_VIA_WHATSAPP = 'whatsapp';

    public const string TARGET_TYPE_USER = 'user';
    public const string TARGET_TYPE_EMAIL = 'email';
    public const string TARGET_TYPE_PHONE = 'phone';
    public const string TARGET_TYPE_WHATSAPP = 'whatsapp';
    public const string TARGET_TYPE_EXTERNAL_CONTACT = 'external_contact';


    #[ORM\Id]
    #[ORM\GeneratedValue( strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(name: 'NF_ID', length: 16)]
    #[Groups(['notification:get'])]
    private ?string $id = null;

    #[ORM\Column(name: 'NF_TYPE', length: 15)]
    #[Assert\Choice(callback: [NotificationType::class, 'getAll'], message: 'Invalid notification type.')] 
    #[Assert\NotBlank(message: 'The type cannot be empty.')]
    #[Assert\NotNull(message: 'The type cannot be empty.')]
    #[Groups(['notification:get'])] 
    private ?string $type = null;

    #[ORM\Column(name: 'NF_SUBJECT', length: 255, nullable: true)]
    #[Groups(['notification:get'])]
    private ?string $subject = null;

    #[ORM\Column(name: 'NF_BODY', type: Types::TEXT)]
    #[Groups(['notification:get'])]
    #[Assert\NotBlank(message: 'The body cannot be empty.')]
    #[Assert\NotNull(message: 'The body cannot be empty.')]
    private ?string $body = null;

    #[ORM\Column(name: 'NF_TITLE', length: 255, nullable: true)]
    #[Groups(['notification:get'])]
    private ?string $title = null;

    #[ORM\Column(name: 'NF_DATA', type: Types::SIMPLE_ARRAY, nullable: true)]
    #[Groups(['notification:get'])]
    private ?array $data = null;
    
    #[ORM\Column(name: 'NF_TEMPLATE', length: 255, nullable: true)]
    #[Groups(['notification:get'])]
    private ?string $template = null;
    
    #[ORM\Column(name: 'NF_TEMPLATE_CONTEXT', type: Types::JSON, nullable: true)]
    #[Groups(['notification:get'])]
    private ?array $templateContext = null;

    #[ORM\Column(name: 'NF_IS_READ')]
    #[Groups(['notification:get'])]
    private ?bool $isRead = false;

    #[ORM\Column(name: 'NF_SENT_VIA', length: 30)]
    #[Groups(['notification:get'])]
    #[Assert\Choice(callback: [Notification::class, 'getAllSentVia'], message: 'Invalid sent via.')]
    #[Assert\NotBlank(message: 'The sent via cannot be empty.')]
    #[Assert\NotNull(message: 'The sent via cannot be empty.')]
    private ?string $sentVia = null;

    #[ORM\Column(name: 'NF_TARGET', length: 255)]
    #[Groups(['notification:get'])]
    #[Assert\NotBlank(message: 'The target cannot be empty.')]
    #[Assert\NotNull(message: 'The target cannot be empty.')]
    private ?string $target = null;

    #[ORM\Column(name: 'NF_TARGET_TYPE', length: 30)]
    #[Groups(['notification:get'])]
    #[Assert\Choice(callback: [Notification::class, 'getAllTargetType'], message: 'Invalid target type.')]
    #[Assert\NotBlank(message: 'The target type cannot be empty.')]
    #[Assert\NotNull(message: 'The target type cannot be empty.')]
    private ?string $targetType = null;

    #[ORM\Column(name: 'NF_READ_AT', nullable: true)]
    #[Groups(['notification:get'])]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(name: 'NF_CREATED_AT')]
    #[Groups(['notification:get'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getSentVia(): ?string
    {
        return $this->sentVia;
    }

    public function setSentVia(string $sentVia): static
    {
        $this->sentVia = $sentVia;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public static function getAllSentVia(): array
    {
        return [
            self::SENT_VIA_SYSTEM,
            self::SENT_VIA_SMS,
            self::SENT_VIA_EMAIL,
            self::SENT_VIA_GMAIL,
            self::SENT_VIA_WHATSAPP,
        ];
    }

    public static function getAllTargetType(): array
    {
        return [
            self::TARGET_TYPE_USER,
            self::TARGET_TYPE_EMAIL,
            self::TARGET_TYPE_PHONE,
            self::TARGET_TYPE_WHATSAPP,
            self::TARGET_TYPE_EXTERNAL_CONTACT,
        ];
    }
    
    public function getTemplate(): ?string
    {
        return $this->template;
    }
    
    public function setTemplate(?string $template): static
    {
        $this->template = $template;
        
        return $this;
    }
    
    public function getTemplateContext(): ?array
    {
        return $this->templateContext;
    }
    
    public function setTemplateContext(?array $templateContext): static
    {
        $this->templateContext = $templateContext;
        
        return $this;
    }
}
