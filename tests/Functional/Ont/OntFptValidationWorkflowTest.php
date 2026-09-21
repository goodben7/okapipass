<?php

namespace App\Tests\Functional\Ont;

use App\Entity\PassDeclaration;
use App\Entity\Profile;
use App\Entity\User;
use App\Model\UserProxyIntertace;
use App\Tests\Functional\Agency\AgencyApiTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OntFptValidationWorkflowTest extends AgencyApiTestCase
{
    public function testOntValidateThenPayDeclaration(): void
    {
        $ws = $this->createPartnerWorkspace('OntValidate');
        $ont = $this->createOntAdmin();
        $declarationId = $this->createSubmittedDeclaration($ws);

        $validated = $this->api(
            'POST',
            '/api/ont/fpt-declarations/'.$declarationId.'/validate',
            $ont['token'],
            null,
            200,
        );
        self::assertSame(PassDeclaration::STATUS_VALIDATED, $validated['status'] ?? null);
        self::assertNotEmpty($validated['validatedAt'] ?? null);

        $paid = $this->api(
            'POST',
            '/api/ont/fpt-declarations/'.$declarationId.'/pay',
            $ont['token'],
            null,
            200,
        );
        self::assertSame(PassDeclaration::STATUS_PAID, $paid['status'] ?? null);
        self::assertNotEmpty($paid['paidAt'] ?? null);
    }

    public function testPayWithoutValidationReturns422(): void
    {
        $ws = $this->createPartnerWorkspace('OntPayEarly');
        $ont = $this->createOntAdmin();
        $declarationId = $this->createSubmittedDeclaration($ws);

        $this->api(
            'POST',
            '/api/ont/fpt-declarations/'.$declarationId.'/pay',
            $ont['token'],
            null,
            422,
        );
    }

    public function testOntRejectDeclarationWithReason(): void
    {
        $ws = $this->createPartnerWorkspace('OntReject');
        $ont = $this->createOntAdmin();
        $declarationId = $this->createSubmittedDeclaration($ws);

        $rejected = $this->api(
            'POST',
            '/api/ont/fpt-declarations/'.$declarationId.'/reject',
            $ont['token'],
            ['reason' => 'Montants incohérents'],
            200,
        );

        self::assertSame(PassDeclaration::STATUS_REJECTED, $rejected['status'] ?? null);
        self::assertSame('Montants incohérents', $rejected['rejectionReason'] ?? null);
        self::assertNotEmpty($rejected['rejectedAt'] ?? null);
    }

    public function testAgencyCanResubmitRejectedDeclaration(): void
    {
        $ws = $this->createPartnerWorkspace('OntResubmit');
        $ont = $this->createOntAdmin();
        $declarationId = $this->createSubmittedDeclaration($ws);

        $this->api(
            'POST',
            '/api/ont/fpt-declarations/'.$declarationId.'/reject',
            $ont['token'],
            ['reason' => 'À corriger'],
            200,
        );

        $resubmitted = $this->api('POST', '/api/ont/fpt-declarations', $ws['token'], [
            'declaration' => '/api/agency/declarations/'.$declarationId,
        ], 200);

        self::assertSame(PassDeclaration::STATUS_SUBMITTED, $resubmitted['status'] ?? null);
        self::assertTrue(empty($resubmitted['rejectionReason'] ?? null));
    }

    public function testPartnerCannotValidate(): void
    {
        $ws = $this->createPartnerWorkspace('OntNoVal');
        $declarationId = $this->createSubmittedDeclaration($ws);

        $this->api(
            'POST',
            '/api/ont/fpt-declarations/'.$declarationId.'/validate',
            $ws['token'],
            null,
            403,
        );
    }

    /**
     * @param array{token: string, offer: \App\Entity\AgencyOffer} $ws
     */
    private function createSubmittedDeclaration(array $ws): string
    {
        $date = $this->travelDate('+4 days');
        $csv = implode("\n", [
            'referenceBillet;date;passengerName;passengerId;origin;destination;ticketPrice;currency',
            "VP-ONT-VAL-{$this->suffix};{$date};Passager Ont;CD-ONT;Kinshasa;Matadi;10000;CDF",
        ]);

        $declaration = $this->api('POST', '/api/agency/declarations/import-csv', $ws['token'], [
            'content' => $csv,
            'label' => 'Import ONT workflow',
        ], 201);

        $id = $declaration['id'] ?? null;
        self::assertNotNull($id);

        $submitted = $this->api('POST', '/api/ont/fpt-declarations', $ws['token'], [
            'declaration' => '/api/agency/declarations/'.$id,
        ], 200);

        self::assertSame(PassDeclaration::STATUS_SUBMITTED, $submitted['status'] ?? null);

        return (string) $id;
    }

    /** @return array{token: string} */
    private function createOntAdmin(): array
    {
        $email = sprintf('ont_admin_wf_%s@ont.test', $this->suffix);
        $password = 'OntPass123!';
        $personType = UserProxyIntertace::PERSON_ONT_ADMIN;

        $profile = $this->em->getRepository(Profile::class)->findOneBy(['personType' => $personType]);
        if (null === $profile) {
            $profile = new Profile();
            $profile->setLabel('ONT Admin');
            $profile->setPersonType($personType);
            $profile->setPermission(['ROLE_ONT_ADMIN']);
            $profile->setActive(true);
            $this->em->persist($profile);
            $this->em->flush();
        }

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('ONT Admin WF');
        $user->setPhone('+2438999'.random_int(100000, 999999));
        $user->setProfile($profile);
        $user->setPersonType($personType);
        $user->setPlainPassword($password);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setCreatedAt(new \DateTimeImmutable('now'));
        $this->em->persist($user);
        $this->em->flush();

        return ['token' => $this->authenticate($email, $password)];
    }
}
