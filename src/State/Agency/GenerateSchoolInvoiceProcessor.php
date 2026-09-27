<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\GenerateSchoolInvoiceDto;
use App\Entity\SchoolInvoice;
use App\Manager\SchoolInvoiceManager;

/** @implements ProcessorInterface<GenerateSchoolInvoiceDto, SchoolInvoice> */
final class GenerateSchoolInvoiceProcessor implements ProcessorInterface
{
    public function __construct(private SchoolInvoiceManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolInvoice
    {
        \assert($data instanceof GenerateSchoolInvoiceDto);

        return $this->manager->generate((string) ($uriVariables['id'] ?? ''), $data);
    }
}
