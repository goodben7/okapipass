<?php

namespace App\Dto\Agency;

use App\Domain\Agency\DeclarationCsvLimits;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ImportPassDeclarationCsvDto
{
    public function __construct(
        #[Assert\Length(max: DeclarationCsvLimits::MAX_CONTENT_BYTES)]
        public ?string $content = null,

        #[Assert\Length(max: 160)]
        public ?string $label = null,

        #[Assert\File(
            maxSize: '2M',
            mimeTypes: [
                'text/csv',
                'text/plain',
                'application/csv',
                'text/x-csv',
                'application/vnd.ms-excel',
            ],
            mimeTypesMessage: 'Please upload a valid CSV file.',
        )]
        public ?UploadedFile $file = null,
    ) {
    }

    #[Assert\Callback]
    public function validateSource(ExecutionContextInterface $context): void
    {
        $hasContent = null !== $this->content && '' !== trim($this->content);
        $hasFile = null !== $this->file;

        if (!$hasContent && !$hasFile) {
            $context->buildViolation('Provide CSV via "content" or "file".')
                ->atPath('content')
                ->addViolation();
        }
    }
}
