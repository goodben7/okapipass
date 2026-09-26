<?php

namespace App\Manager;

use App\Dto\Traveler\CreateTravelerBeneficiaryDto;
use App\Dto\Traveler\UpdateTravelerBeneficiaryDto;
use App\Entity\TravelerBeneficiary;
use App\Entity\User;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\TravelerBeneficiaryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TravelerBeneficiaryManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private TravelerBeneficiaryRepository $beneficiaries,
    ) {
    }

    public function create(User $owner, CreateTravelerBeneficiaryDto $dto): TravelerBeneficiary
    {
        $relation = strtoupper(trim((string) $dto->relation));
        if (!\in_array($relation, TravelerBeneficiary::getRelationsAsList(), true)) {
            throw new UnprocessableEntityException('Invalid relation.');
        }

        $beneficiary = new TravelerBeneficiary();
        $beneficiary->setOwner($owner);
        $beneficiary->setFullName((string) $dto->fullName);
        $beneficiary->setPhone((string) $dto->phone);
        $beneficiary->setRelation($relation);
        $beneficiary->setDateOfBirth(null !== $dto->dateOfBirth ? new \DateTimeImmutable($dto->dateOfBirth) : null);
        $beneficiary->setIdDocument($dto->idDocument);

        $this->em->persist($beneficiary);
        $this->em->flush();

        return $beneficiary;
    }

    public function update(User $owner, TravelerBeneficiary $beneficiary, UpdateTravelerBeneficiaryDto $dto): TravelerBeneficiary
    {
        $this->assertOwner($owner, $beneficiary);

        if (null !== $dto->fullName) {
            $beneficiary->setFullName($dto->fullName);
        }
        if (null !== $dto->phone) {
            $beneficiary->setPhone($dto->phone);
        }
        if (null !== $dto->relation) {
            $relation = strtoupper(trim($dto->relation));
            if (!\in_array($relation, TravelerBeneficiary::getRelationsAsList(), true)) {
                throw new UnprocessableEntityException('Invalid relation.');
            }
            $beneficiary->setRelation($relation);
        }
        if (null !== $dto->dateOfBirth) {
            $beneficiary->setDateOfBirth('' === $dto->dateOfBirth ? null : new \DateTimeImmutable($dto->dateOfBirth));
        }
        if (null !== $dto->idDocument) {
            $beneficiary->setIdDocument('' === $dto->idDocument ? null : $dto->idDocument);
        }

        $this->em->flush();

        return $beneficiary;
    }

    public function delete(User $owner, TravelerBeneficiary $beneficiary): void
    {
        $this->assertOwner($owner, $beneficiary);
        $this->em->remove($beneficiary);
        $this->em->flush();
    }

    /** @return list<TravelerBeneficiary> */
    public function listFor(User $owner): array
    {
        return $this->beneficiaries->findByOwner($owner);
    }

    public function getOwned(User $owner, string $id): TravelerBeneficiary
    {
        $beneficiary = $this->beneficiaries->find($id);
        if (!$beneficiary instanceof TravelerBeneficiary) {
            throw new UnavailableDataException('Beneficiary not found.');
        }
        $this->assertOwner($owner, $beneficiary);

        return $beneficiary;
    }

    private function assertOwner(User $owner, TravelerBeneficiary $beneficiary): void
    {
        if ($beneficiary->getOwner()?->getId() !== $owner->getId()) {
            throw new UnavailableDataException('Beneficiary not found.');
        }
    }
}
