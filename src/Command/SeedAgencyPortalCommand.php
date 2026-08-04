<?php

namespace App\Command;

use App\Entity\Agency;
use App\Entity\AgencyOffer;
use App\Entity\AgencyTransport;
use App\Entity\GoPass;
use App\Entity\IssuedOkapiPass;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\EntityType;
use App\Model\UserProxyIntertace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Full Agency Portal demo seed (Bruno / local).
 *
 * Creates (idempotent):
 * - PARTNER profile (if missing)
 * - GoPass ROUTIER tariff (if missing)
 * - Demo agency + user agency@gmail.com / 12345678
 * - 1 transport + 1 offer
 * - Sample issued OP-… passes
 */
#[AsCommand(
    name: 'app:seed-agency-portal',
    description: 'Seed Agency Portal demo: partner user, agency, transport, offer, Pass ONT',
)]
class SeedAgencyPortalCommand extends Command
{
    public const string DEMO_EMAIL = 'agency@gmail.com';
    public const string DEMO_PASSWORD = '12345678';
    public const string DEMO_AGENCY_NAME = 'Voyages Plus';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'email',
            null,
            InputOption::VALUE_REQUIRED,
            'Partner login email',
            self::DEMO_EMAIL,
        );
        $this->addOption(
            'password',
            null,
            InputOption::VALUE_REQUIRED,
            'Partner login password',
            self::DEMO_PASSWORD,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getOption('email')));
        $password = (string) $input->getOption('password');

        $profile = $this->ensurePartnerProfile($io);
        $this->ensureRoutierTariff($io);
        $passes = $this->ensureIssuedPasses($io);
        $agency = $this->ensureDemoAgency($io, $email);
        $user = $this->ensurePartnerUser($io, $agency, $profile, $email, $password);
        $agency->setUserId($user->getId());
        $this->em->flush();

        [$transports, $offers] = $this->ensureTransportAndOffer($agency);

        $this->em->flush();

        $io->success([
            'Agency Portal seed OK',
            sprintf('Login: %s / %s', $email, $password),
            sprintf('Agency: %s (%s)', $agency->getName(), $agency->getId()),
            sprintf('Transports created: %d | Offers created: %d | Passes created: %d', $transports, $offers, $passes),
        ]);

        return Command::SUCCESS;
    }

    private function ensurePartnerProfile(SymfonyStyle $io): Profile
    {
        $profile = $this->em->getRepository(Profile::class)->findOneBy([
            'personType' => UserProxyIntertace::PERSON_PARTNER,
        ]);
        if (null !== $profile) {
            return $profile;
        }

        $profile = new Profile();
        $profile->setLabel('Partner');
        $profile->setPersonType(UserProxyIntertace::PERSON_PARTNER);
        $profile->setPermission(['ROLE_PARTNER']);
        $profile->setActive(true);
        $this->em->persist($profile);
        $this->em->flush();
        $io->writeln('Created PARTNER profile.');

        return $profile;
    }

    private function ensureRoutierTariff(SymfonyStyle $io): void
    {
        $gopass = $this->em->getRepository(GoPass::class)->findOneBy(['code' => 'ROUTIER']);
        if (null !== $gopass) {
            return;
        }

        $gopass = new GoPass();
        $gopass->setCode('ROUTIER');
        $gopass->setLabel('Pass routier OkapiPass');
        $gopass->setTransportType(GoPass::TRANSPORT_ROUTIER);
        $gopass->setPrice(3000);
        $gopass->setCurrency('CDF');
        $gopass->setActive(true);
        $this->em->persist($gopass);
        $this->em->flush();
        $io->writeln('Created GoPass ROUTIER @ 3000 CDF.');
    }

    private function ensureIssuedPasses(SymfonyStyle $io): int
    {
        $specs = [
            ['ref' => 'OP-MOCK-2044', 'holder' => 'Ilunga Sarah', 'status' => IssuedOkapiPass::STATUS_ACTIVE],
            ['ref' => 'OP-ABC123', 'holder' => 'Kabongo Jean', 'status' => IssuedOkapiPass::STATUS_ACTIVE],
            ['ref' => 'OP-EXPIRED-1', 'holder' => 'Test Expire', 'status' => IssuedOkapiPass::STATUS_EXPIRED],
        ];
        $created = 0;
        foreach ($specs as $spec) {
            $existing = $this->em->getRepository(IssuedOkapiPass::class)->findOneBy(['reference' => $spec['ref']]);
            if (null !== $existing) {
                continue;
            }
            $pass = new IssuedOkapiPass();
            $pass->setReference($spec['ref']);
            $pass->setHolderName($spec['holder']);
            $pass->setStatus($spec['status']);
            $this->em->persist($pass);
            ++$created;
        }
        if ($created > 0) {
            $this->em->flush();
            $io->writeln(sprintf('Created %d issued Pass(es).', $created));
        }

        return $created;
    }

    private function ensureDemoAgency(SymfonyStyle $io, string $email): Agency
    {
        $agency = $this->em->getRepository(Agency::class)->findOneBy(['email' => $email])
            ?? $this->em->getRepository(Agency::class)->findOneBy(['name' => self::DEMO_AGENCY_NAME]);

        if (null !== $agency) {
            return $agency;
        }

        $agency = new Agency();
        $agency->setName(self::DEMO_AGENCY_NAME);
        $agency->setEmail($email);
        $agency->setPhone('+243810000042');
        $agency->setAddress('Kinshasa, RDC');
        $agency->setLicenseNumber('AGT-ONT-2024-042');
        $agency->setDefaultCurrency(Agency::DEFAULT_CURRENCY);
        $agency->setSupportedCurrencies([Agency::DEFAULT_CURRENCY, 'USD']);
        $agency->setType(Agency::TYPE_ROAD);
        $agency->setStatus(Agency::STATUS_ACTIVE);
        $agency->setCreatedAt(new \DateTimeImmutable('now'));
        $this->em->persist($agency);
        $this->em->flush();
        $io->writeln(sprintf('Created agency %s (%s).', $agency->getName(), $agency->getId()));

        return $agency;
    }

    private function ensurePartnerUser(
        SymfonyStyle $io,
        Agency $agency,
        Profile $profile,
        string $email,
        string $password,
    ): User {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $user) {
            $user->setPersonType(UserProxyIntertace::PERSON_PARTNER);
            $user->setProfile($profile);
            $user->setHolderId($agency->getId());
            $user->setHolderType(EntityType::AGENCY);
            $user->setPlainPassword($password);
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $user->setIsConfirmed(true);
            $user->setLocked(false);
            $user->setDeleted(false);
            $io->writeln(sprintf('Updated partner user %s (password reset).', $email));

            return $user;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName(self::DEMO_AGENCY_NAME);
        $user->setPhone($agency->getPhone());
        $user->setProfile($profile);
        $user->setPersonType(UserProxyIntertace::PERSON_PARTNER);
        $user->setHolderId($agency->getId());
        $user->setHolderType(EntityType::AGENCY);
        $user->setPlainPassword($password);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setIsConfirmed(true);
        $user->setCreatedAt(new \DateTimeImmutable('now'));
        $this->em->persist($user);
        $this->em->flush();
        $io->writeln(sprintf('Created partner user %s.', $email));

        return $user;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function ensureTransportAndOffer(Agency $agency): array
    {
        $createdTransports = 0;
        $createdOffers = 0;

        $plate = sprintf('OKP-%s', strtoupper(substr(md5((string) $agency->getId()), 0, 4)));
        $transport = $this->em->getRepository(AgencyTransport::class)->findOneBy([
            'agency' => $agency,
            'plateNumber' => $plate,
        ]);

        if (null === $transport) {
            $transport = new AgencyTransport();
            $transport->setAgency($agency);
            $transport->setLabel(sprintf('%s Bus 01', $agency->getName()));
            $transport->setKind(AgencyTransport::KIND_BUS);
            $transport->setPlateNumber($plate);
            $transport->setCapacity(44);
            $transport->setStatus(AgencyTransport::STATUS_ACTIVE);
            $this->em->persist($transport);
            ++$createdTransports;
        }

        $offerLabel = sprintf('%s Express', $agency->getName());
        $offer = $this->em->getRepository(AgencyOffer::class)->findOneBy([
            'agency' => $agency,
            'label' => $offerLabel,
        ]);

        if (null === $offer) {
            $offer = new AgencyOffer();
            $offer->setAgency($agency);
            $offer->setTransport($transport);
            $offer->setLabel($offerLabel);
            $offer->setOrigin('Kinshasa');
            $offer->setDestination('Lubumbashi');
            $offer->setTicketPrice(85000);
            $offer->setCurrency($agency->getDefaultCurrency());
            $offer->setDepartureTime('06:00');
            $offer->setDurationMinutes(1440);
            $offer->setActive(true);
            $this->em->persist($offer);
            ++$createdOffers;
        }

        return [$createdTransports, $createdOffers];
    }
}
