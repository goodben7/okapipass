<?php

namespace App\Entity;

use App\Enum\EntityType;
use ApiPlatform\Metadata\Get;
use App\Doctrine\IdGenerator;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use App\Model\RessourceInterface;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\DocumentRepository;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use Symfony\Component\HttpFoundation\File\File;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\Serializer\Attribute\Groups;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Doctrine\Common\State\PersistProcessor;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: DocumentRepository::class)]  
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`document`')]
#[ApiResource(
    normalizationContext: ['groups' => 'document:get'],
    operations: [
        new Get(
            //security: 'is_granted("ROLE_DOC_DETAILS")',
            provider: ItemProvider::class,
        ),
        new GetCollection(
            //security: 'is_granted("ROLE_DOC_LIST")',
            provider: CollectionProvider::class,
        ),
        new Post(
            denormalizationContext: ['groups' => 'document:post'],
            security: 'is_granted("ROLE_DOC_CREATE")',
            inputFormats: ['multipart' => ['multipart/form-data']],
            processor: PersistProcessor::class,
        ),
        new Delete(
            security: 'is_granted("ROLE_DOC_DELETE")',
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'holderId' => 'exact',
    'holderType' => 'exact',
    'type' => 'exact',
    'title' => 'ipartial',
    'documentRefNumber' => 'start',
])]
#[ApiFilter(OrderFilter::class, properties: ['uploadedAt', 'updatedAt'])]
#[ApiFilter(DateFilter::class, properties: ['uploadedAt', 'updatedAt'])]
class Document implements RessourceInterface
{
    public const string  ID_PREFIX = "DC";


    public const string TYPE_PHOTO = "PHOTO";

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(IdGenerator::class)]
    #[ORM\Column(length: 16, name: 'DC_ID')]
    #[Groups(['document:get'])]
    private ?string $id = null;

    #[ORM\Column(length: 5, name: 'DC_TYPE')]
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Assert\Choice(callback: [self::class, 'getTypeAsChoices'])]
    #[Groups(['document:get', 'document:post'])]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true, name: 'DC_REF_NUMBER')]
    #[Groups(['document:get', 'document:post'])]
    private ?string $documentRefNumber = null;

    #[ORM\Column(name: 'DC_UPLOADED_AT')]
    #[Groups(['document:get'])]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(length: 120, nullable: true, name: 'DC_TITLE')]
    #[Groups(['document:get', 'document:post'])]
    private ?string $title = null;

    #[ORM\Column(nullable: true, name: 'DC_UPDATED_AT')]
    #[Groups(['document:get'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, name: 'DC_HOLDER_TYPE')]
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Assert\Choice(callback: [EntityType::class, 'getAll'], message: 'Invalid holder type.')]
    #[Groups(['document:get', 'document:post'])]
    private ?string $holderType = null;

    #[ORM\Column(length: 16, name: 'DC_HOLDER_ID')]
    #[Assert\NotNull()]
    #[Assert\NotBlank()]
    #[Groups(['document:get', 'document:post'])]
    private ?string $holderId = null;

    #[Groups(['document:get', 'document:post'])]
    #[Vich\UploadableField(mapping: 'media_object', fileNameProperty: 'filePath', size: 'fileSize')]
    private ?File $file = null;

    #[ORM\Column(length: 255, nullable: true, name: 'DC_FILE_PATH')]
    #[Groups(['document:get'])]
    private ?string $filePath = null;

    #[ORM\Column(nullable: true, name: 'DC_FILE_SIZE')]
    #[Groups(['document:get'])]
    private ?int $fileSize = null;

    #[Groups(['document:get'])]
    private ?string $contentUrl;

    #[Groups(['document:get', 'document:post'])]
    #[Vich\UploadableField(mapping: 'media_object', fileNameProperty: 'filePathSecondary', size: 'fileSizeSecondary')]
    private ?File $fileSecondary = null;

    #[ORM\Column(length: 255, nullable: true, name: 'DC_FILE_PATH_SECONDARY')]
    #[Groups(['document:get'])]
    private ?string $filePathSecondary = null;

    #[ORM\Column(nullable: true, name: 'DC_FILE_SIZE_SECONDARY')]
    #[Groups(['document:get'])]
    private ?int $fileSizeSecondary = null;

    #[Groups(['document:get'])]
    private ?string $contentUrlSecondary;

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

    public function getDocumentRefNumber(): ?string
    {
        return $this->documentRefNumber;
    }

    public function setDocumentRefNumber(?string $documentRefNumber): static
    {
        $this->documentRefNumber = $documentRefNumber;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getHolderType(): ?string
    {
        return $this->holderType;
    }

    public function setHolderType(string $holderType): static
    {
        $this->holderType = $holderType;

        return $this;
    }

    public function getHolderId(): ?string
    {
        return $this->holderId;
    }

    public function setHolderId(string $holderId): static
    {
        $this->holderId = $holderId;

        return $this;
    }

    /**
     * Get the value of contentUrl
     */ 
    public function getContentUrl(): string|null
    {
        return $this->contentUrl;
    }

    /**
     * Set the value of contentUrl
     *
     * @return  self
     */ 
    public function setContentUrl(?string $contentUrl): static
    {
        $this->contentUrl = $contentUrl;

        return $this;
    }

    /**
     * Get the value of contentUrlSecondary
     */ 
    public function getContentUrlSecondary(): string|null
    {
        return $this->contentUrlSecondary;
    }

    /**
     * Set the value of contentUrlSecondary
     *
     * @return  self
     */ 
    public function setContentUrlSecondary(?string $contentUrlSecondary)
    {
        $this->contentUrlSecondary = $contentUrlSecondary;

        return $this;
    }

    /**
     * Get the value of file
     */ 
    public function getFile(): File|null
    {
        return $this->file;
    }

    /**
     * Set the value of file
     *
     * @return  self
     */ 
    public function setFile(?File $file): static
    {
        $this->file = $file;

        if (null !== $file) {
            $this->updatedAt = new \DateTimeImmutable('now');
        }

        return $this;
    }

    /**
     * Get the value of filePath
     */ 
    public function getFilePath(): string|null
    {
        return $this->filePath;
    }

    /**
     * Set the value of filePath
     *
     * @return  self
     */ 
    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * Get the value of fileSize
     */ 
    public function getFileSize(): int|null
    {
        return $this->fileSize;
    }

    /**
     * Set the value of fileSize
     *
     * @return  self
     */ 
    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    /**
     * Get the value of fileSecondary
     */ 
    public function getFileSecondary(): File|null
    {
        return $this->fileSecondary;
    }

    /**
     * Set the value of fileSecondary
     *
     * @return  self
     */ 
    public function setFileSecondary(?File $fileSecondary): static
    {
        $this->fileSecondary = $fileSecondary;

        if (null !== $fileSecondary) {
            $this->updatedAt = new \DateTimeImmutable('now');
        }

        return $this;
    }

    /**
     * Get the value of filePathSecondary
     */ 
    public function getFilePathSecondary(): string|null
    {
        return $this->filePathSecondary;
    }

    /**
     * Set the value of filePathSecondary
     *
     * @return  self
     */ 
    public function setFilePathSecondary(?string $filePathSecondary): static
    {
        $this->filePathSecondary = $filePathSecondary;

        return $this;
    }

    /**
     * Get the value of fileSizeSecondary
     */ 
    public function getFileSizeSecondary(): int|null
    {
        return $this->fileSizeSecondary;
    }

    /**
     * Set the value of fileSizeSecondary
     *
     * @return  self
     */ 
    public function setFileSizeSecondary(?int $fileSizeSecondary): static
    {
        $this->fileSizeSecondary = $fileSizeSecondary;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function getTypeAsChoices(): array
    {
        return [
            "Photo" => self::TYPE_PHOTO,
        ];
    }
}
