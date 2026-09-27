<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateSchoolDepartureDto;
use App\Dto\Agency\RecordSchoolAttendanceDto;
use App\Entity\AgencyEmbarkation;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Entity\SchoolAttendance;
use App\Entity\SchoolContract;
use App\Entity\User;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\AgencyEmbarkationRepository;
use App\Repository\AgencyTransportRepository;
use App\Repository\SchoolAttendanceRepository;
use App\Repository\SchoolStudentRepository;
use App\Service\Agency\AgencyContext;
use Doctrine\ORM\EntityManagerInterface;

final class SchoolBusManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private SchoolContractManager $contracts,
        private SchoolStudentManager $students,
        private SchoolStudentRepository $studentRepository,
        private SchoolAttendanceRepository $attendances,
        private AgencyEmbarkationRepository $embarkations,
        private AgencyTransportRepository $transports,
        private AgencyDriverManager $drivers,
    ) {
    }

    /**
     * @return array{
     *     contractId: string,
     *     date: string,
     *     embarkationId: ?string,
     *     transportId: ?string,
     *     transportLabel: ?string,
     *     plateNumber: ?string,
     *     students: list<array{
     *         id: string,
     *         fullName: string,
     *         phone: ?string,
     *         grade: ?string,
     *         pickupStopCode: ?string,
     *         dropoffStopCode: ?string,
     *         attendanceStatus: ?string,
     *         attendanceId: ?string
     *     }>
     * }
     */
    public function roster(string $contractId, string $dateRaw): array
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $contract = $this->contracts->requireOwnedContract($contractId);
        $date = $this->parseDate($dateRaw);
        $offer = $contract->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('School contract has no offer.');
        }

        $embarkation = $this->embarkations->findOneForOfferOnDate($offer, $date);
        $transport = $embarkation?->getTransport();
        $attendanceByStudent = [];
        foreach ($this->attendances->findByContractOnDate($contract, $date) as $row) {
            $sid = $row->getStudent()?->getId();
            if (null !== $sid) {
                $attendanceByStudent[$sid] = $row;
            }
        }

        $students = [];
        foreach ($this->studentRepository->findActiveByContract($contract) as $student) {
            $sid = (string) $student->getId();
            $attendance = $attendanceByStudent[$sid] ?? null;
            $students[] = [
                'id' => $sid,
                'fullName' => (string) $student->getFullName(),
                'phone' => $student->getPhone(),
                'grade' => $student->getGrade(),
                'pickupStopCode' => $student->getPickupStopCode(),
                'dropoffStopCode' => $student->getDropoffStopCode(),
                'attendanceStatus' => $attendance?->getStatus(),
                'attendanceId' => $attendance?->getId(),
            ];
        }

        return [
            'contractId' => (string) $contract->getId(),
            'date' => $date->format('Y-m-d'),
            'embarkationId' => $embarkation?->getId(),
            'transportId' => $transport?->getId(),
            'transportLabel' => $transport?->getLabel(),
            'plateNumber' => $transport?->getPlateNumber(),
            'students' => $students,
        ];
    }

    public function recordAttendance(RecordSchoolAttendanceDto $dto): SchoolAttendance
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $contract = $this->contracts->requireOwnedContract((string) $dto->contractId);
        $student = $this->students->requireOwnedStudent((string) $dto->studentId, $agency->getId());

        if ($student->getContract()?->getId() !== $contract->getId()) {
            throw new UnprocessableEntityException('Student does not belong to this school contract.');
        }
        if (!$student->isActive()) {
            throw new UnprocessableEntityException('Cannot record attendance for an inactive student.');
        }

        $date = $this->parseDate((string) $dto->date);
        $status = strtoupper((string) $dto->status);
        if (!\in_array($status, SchoolAttendance::getStatusesAsList(), true)) {
            throw new UnprocessableEntityException(sprintf('Unsupported attendance status "%s".', $status));
        }

        $attendance = $this->attendances->findOneForStudentOnDate($student, $date);
        if (!$attendance instanceof SchoolAttendance) {
            $attendance = new SchoolAttendance();
            $attendance->setAgency($agency);
            $attendance->setContract($contract);
            $attendance->setStudent($student);
            $attendance->setAttendanceDate($date);
            $this->em->persist($attendance);
        }

        $attendance->setStatus($status);
        $attendance->setRecordedAt(new \DateTimeImmutable('now'));
        $user = $this->agencyContext->getUser();
        if ($user instanceof User) {
            $attendance->setRecordedBy($user);
        }

        $this->em->flush();

        return $attendance;
    }

    public function createOrFindDeparture(CreateSchoolDepartureDto $dto): AgencyEmbarkation
    {
        $this->agencyContext->requirePermission(AgencyPermission::SCHOOL_WRITE);
        $agency = $this->agencyContext->requireAgency();
        $contract = $this->contracts->requireOwnedContract((string) $dto->contractId);
        $offer = $contract->getOffer();
        if (!$offer instanceof AgencyOffer) {
            throw new UnprocessableEntityException('School contract has no offer.');
        }

        $date = $this->parseDate((string) $dto->date);
        $existing = $this->embarkations->findOneForOfferOnDate($offer, $date);
        if ($existing instanceof AgencyEmbarkation) {
            return $existing;
        }

        $transport = $this->resolveTransportForDeparture($dto->transportId, $contract, $offer, $agency->getId());
        $embarkation = new AgencyEmbarkation();
        $embarkation->setAgency($agency);
        $embarkation->setLabel(sprintf(
            'Bus scolaire %s — %s',
            (string) $contract->getSchoolName(),
            $date->format('Y-m-d')
        ));
        $embarkation->setOffer($offer);
        $embarkation->setTransport($transport);
        $embarkation->setDepartureDate($date);
        $embarkation->setDepartureTime((string) ($offer->getDepartureTime() ?? '06:00'));
        $embarkation->setDriver($this->drivers->resolveForAssignment($dto->driverId, $agency->getId()));
        $embarkation->setStatus(AgencyEmbarkation::STATUS_PLANNED);
        $embarkation->setNotes(sprintf('School contract %s', $contract->getId()));

        $this->em->persist($embarkation);
        $this->em->flush();

        return $embarkation;
    }

    /**
     * Null = unassigned departure (assign later via assign-transport).
     */
    private function resolveTransportForDeparture(
        ?string $transportId,
        SchoolContract $contract,
        AgencyOffer $offer,
        ?string $agencyId,
    ): ?AgencyTransport {
        if (null !== $transportId && '' !== trim($transportId)) {
            $id = $this->extractId($transportId);
            $transport = $this->transports->find($id);
            if (!$transport instanceof AgencyTransport || $transport->getAgency()?->getId() !== $agencyId) {
                throw new UnavailableDataException(sprintf('Transport "%s" not found.', $id));
            }

            return $transport;
        }

        $fromContract = $contract->getTransport();
        if ($fromContract instanceof AgencyTransport) {
            return $fromContract;
        }

        $fromOffer = $offer->getTransport();
        if ($fromOffer instanceof AgencyTransport) {
            return $fromOffer;
        }

        return null;
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new UnprocessableEntityException('date (YYYY-MM-DD) is required.');
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (false === $date) {
            throw new UnprocessableEntityException('Invalid date.');
        }

        return $date->setTime(0, 0);
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
