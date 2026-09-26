<?php

namespace App\Service\Auth;

use App\Contract\AgencySmsSenderInterface;
use App\Entity\OtpChallenge;
use App\Entity\Profile;
use App\Entity\User;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnprocessableEntityException;
use App\Model\UserProxyIntertace;
use App\Repository\OtpChallengeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TravelerOtpService
{
    public const string PURPOSE_LOGIN = 'TRAVELER_LOGIN';
    private const int TTL_SECONDS = 600;
    private const int MAX_ATTEMPTS = 5;

    public function __construct(
        private EntityManagerInterface $em,
        private OtpChallengeRepository $challenges,
        private AgencySmsSenderInterface $smsSender,
        private JWTTokenManagerInterface $jwt,
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /**
     * @return array{phone: string, expiresAt: string, debugCode?: string}
     */
    public function request(string $rawPhone): array
    {
        $phone = $this->normalizePhone($rawPhone);
        $code = sprintf('%06d', random_int(0, 999999));

        $challenge = new OtpChallenge();
        $challenge->setPhone($phone);
        $challenge->setPurpose(self::PURPOSE_LOGIN);
        $challenge->setCodeHash($this->hashCode($phone, $code));
        $challenge->setExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', self::TTL_SECONDS)));
        $challenge->setAttempts(0);

        $this->em->persist($challenge);
        $this->em->flush();

        $this->smsSender->send($phone, sprintf('OkapiPass: votre code est %s (valide 10 min).', $code));

        $payload = [
            'phone' => $phone,
            'expiresAt' => $challenge->getExpiresAt()?->format(\DateTimeInterface::ATOM) ?? '',
        ];
        if (\in_array($this->environment, ['dev', 'test'], true)) {
            $payload['debugCode'] = $code;
        }

        return $payload;
    }

    /**
     * @return array{token: string, phone: string, userId: string}
     */
    public function verify(string $rawPhone, string $code): array
    {
        $phone = $this->normalizePhone($rawPhone);
        $code = trim($code);
        if (!preg_match('/^\d{4,8}$/', $code)) {
            throw new UnprocessableEntityException('Invalid OTP code format.');
        }

        $challenge = $this->challenges->findLatestOpen($phone, self::PURPOSE_LOGIN);
        if (!$challenge instanceof OtpChallenge) {
            throw new UnauthorizedActionException('No active OTP challenge for this phone.');
        }
        if ($challenge->getExpiresAt() < new \DateTimeImmutable('now')) {
            throw new UnauthorizedActionException('OTP expired. Request a new code.');
        }
        if ($challenge->getAttempts() >= self::MAX_ATTEMPTS) {
            throw new UnauthorizedActionException('Too many OTP attempts. Request a new code.');
        }

        $challenge->setAttempts($challenge->getAttempts() + 1);
        if (!hash_equals((string) $challenge->getCodeHash(), $this->hashCode($phone, $code))) {
            $this->em->flush();
            throw new UnauthorizedActionException('Invalid OTP code.');
        }

        $challenge->setConsumedAt(new \DateTimeImmutable('now'));
        $user = $this->findOrCreateTraveler($phone);
        $this->em->flush();

        return [
            'token' => $this->jwt->create($user),
            'phone' => $phone,
            'userId' => (string) $user->getId(),
        ];
    }

    public function normalizePhone(string $raw): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($raw)) ?? '';
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }
        if (preg_match('/^0\d{8,12}$/', $phone)) {
            $phone = '+243'.substr($phone, 1);
        }
        if (!str_starts_with($phone, '+') && preg_match('/^243\d{8,10}$/', $phone)) {
            $phone = '+'.$phone;
        }
        if (!preg_match('/^\+\d{8,15}$/', $phone)) {
            throw new UnprocessableEntityException('Invalid phone number. Use international format (+243…).');
        }
        // User.phone column is VARCHAR(15)
        if (\strlen($phone) > 15) {
            throw new UnprocessableEntityException('Phone number is too long (max 15 characters).');
        }

        return $phone;
    }

    private function hashCode(string $phone, string $code): string
    {
        return hash('sha256', $phone.'|'.$code);
    }

    private function findOrCreateTraveler(string $phone): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['phone' => $phone]);
        if ($user instanceof User) {
            if (UserProxyIntertace::PERSON_TRAVELER !== $user->getPersonType()
                && !\in_array('ROLE_TRAVELER', $user->getRoles(), true)
            ) {
                // Existing partner/ONT with same phone — still allow traveler JWT only if person is traveler
                if (UserProxyIntertace::PERSON_TRAVELER !== $user->getPersonType()) {
                    throw new UnprocessableEntityException('This phone is already linked to a non-traveler account.');
                }
            }

            return $user;
        }

        $profile = $this->em->getRepository(Profile::class)->findOneBy([
            'personType' => UserProxyIntertace::PERSON_TRAVELER,
        ]);
        if (null === $profile) {
            $profile = new Profile();
            $profile->setLabel('Voyageur');
            $profile->setPersonType(UserProxyIntertace::PERSON_TRAVELER);
            $profile->setPermission(['ROLE_TRAVELER']);
            $profile->setActive(true);
            $this->em->persist($profile);
        }

        $user = new User();
        $user->setPhone($phone);
        $user->setDisplayName('Voyageur '.$phone);
        $user->setPersonType(UserProxyIntertace::PERSON_TRAVELER);
        $user->setProfile($profile);
        $user->setCreatedAt(new \DateTimeImmutable('now'));
        $plain = bin2hex(random_bytes(16));
        $user->setPlainPassword($plain);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        $this->em->persist($user);

        return $user;
    }
}
