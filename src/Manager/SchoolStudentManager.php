<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateSchoolStudentDto;
use App\Dto\Agency\UpdateSchoolStudentDto;
use App\Entity\SchoolContract;
use App\Entity\SchoolStudent;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\SchoolContractRepository;
use App\Repository\SchoolStudentRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class SchoolStudentManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private SchoolStudentRepository $students,
        private SchoolContractRepository $contracts,
    ) {
    }

    public function create(CreateSchoolStudentDto $dto): SchoolStudent
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $contract = $this->resolveContract((string) $dto->contract, $agency->getId());

        $student = new SchoolStudent();
        $student->setAgency($agency);
        $student->setContract($contract);
        $student->setFullName((string) $dto->fullName);
        $student->setPhone($dto->phone);
        $student->setGrade($dto->grade);
        $student->setPickupStopCode($dto->pickupStopCode);
        $student->setDropoffStopCode($dto->dropoffStopCode);
        $student->setActive($dto->active ?? true);
        $student->setExternalRef($dto->externalRef);

        $this->assertStopCodes($contract, $student);

        $this->em->persist($student);
        $this->em->flush();

        return $student;
    }

    public function update(SchoolStudent $student, UpdateSchoolStudentDto $dto): SchoolStudent
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $this->agencyContext->assertOwns($student->getAgency());
        $agencyId = $student->getAgency()?->getId();

        if (null !== $dto->contract) {
            $student->setContract($this->resolveContract($dto->contract, $agencyId));
        }
        if (null !== $dto->fullName) {
            $student->setFullName($dto->fullName);
        }
        if (null !== $dto->phone) {
            $student->setPhone($dto->phone);
        }
        if (null !== $dto->grade) {
            $student->setGrade($dto->grade);
        }
        if (null !== $dto->pickupStopCode) {
            $student->setPickupStopCode($dto->pickupStopCode);
        }
        if (null !== $dto->dropoffStopCode) {
            $student->setDropoffStopCode($dto->dropoffStopCode);
        }
        if (null !== $dto->active) {
            $student->setActive($dto->active);
        }
        if (null !== $dto->externalRef) {
            $student->setExternalRef($dto->externalRef);
        }

        $contract = $student->getContract();
        if ($contract instanceof SchoolContract) {
            $this->assertStopCodes($contract, $student);
        }

        $this->em->flush();

        return $student;
    }

    public function delete(SchoolStudent $student): void
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $this->agencyContext->assertOwns($student->getAgency());
        $this->em->remove($student);
        $this->em->flush();
    }

    public function requireOwnedStudent(string $studentId, ?string $agencyId = null): SchoolStudent
    {
        $agencyId ??= $this->agencyContext->requireAgency()->getId();
        $student = $this->students->find($this->extractId($studentId));
        if (!$student instanceof SchoolStudent || $student->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('School student "%s" not found.', $studentId));
        }

        return $student;
    }

    private function resolveContract(string $ref, ?string $agencyId): SchoolContract
    {
        $id = $this->extractId($ref);
        $contract = $this->contracts->find($id);
        if (!$contract instanceof SchoolContract || $contract->getAgency()?->getId() !== $agencyId) {
            throw new UnavailableDataException(sprintf('School contract "%s" not found.', $id));
        }

        return $contract;
    }

    private function assertStopCodes(SchoolContract $contract, SchoolStudent $student): void
    {
        $codes = $contract->getStopCodes();
        if ([] === $codes) {
            return;
        }

        $pickup = $student->getPickupStopCode();
        if (null !== $pickup && '' !== $pickup && !$contract->hasStopCode($pickup)) {
            throw new UnprocessableEntityException(sprintf(
                'pickupStopCode "%s" is not defined in contract stops.',
                $pickup
            ));
        }

        $dropoff = $student->getDropoffStopCode();
        if (null !== $dropoff && '' !== $dropoff && !$contract->hasStopCode($dropoff)) {
            throw new UnprocessableEntityException(sprintf(
                'dropoffStopCode "%s" is not defined in contract stops.',
                $dropoff
            ));
        }
    }

    private function extractId(string $ref): string
    {
        $ref = trim($ref);
        if (str_contains($ref, '/')) {
            $parts = explode('/', rtrim($ref, '/'));

            return (string) end($parts);
        }

        return $ref;
    }
}
