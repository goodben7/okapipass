<?php

namespace App\Tests\Functional\Agency;

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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AgencyApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected string $suffix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->suffix = substr(bin2hex(random_bytes(4)), 0, 8);
        $this->ensureRoutierTariff();
    }

    /**
     * @return array{agency: Agency, user: User, email: string, password: string, token: string, transport: AgencyTransport, offer: AgencyOffer}
     */
    protected function createPartnerWorkspace(string $label = 'Agency', int $capacity = 8): array
    {
        $email = sprintf('%s_%s@agency.test', strtolower($label), $this->suffix);
        $password = 'TestPass123!';

        $profile = $this->em->getRepository(Profile::class)->findOneBy(['personType' => UserProxyIntertace::PERSON_PARTNER]);
        if (null === $profile) {
            $profile = new Profile();
            $profile->setLabel('Partner');
            $profile->setPersonType(UserProxyIntertace::PERSON_PARTNER);
            $profile->setPermission(['ROLE_PARTNER']);
            $profile->setActive(true);
            $this->em->persist($profile);
            $this->em->flush();
        }

        $agency = new Agency();
        $agency->setName(sprintf('%s %s', $label, $this->suffix));
        $agency->setEmail($email);
        $agency->setPhone(sprintf('+2438%08d', random_int(10000000, 99999999)));
        $agency->setAddress('Kinshasa');
        $agency->setLicenseNumber(sprintf('AGT-TEST-%s', strtoupper($this->suffix)));
        $agency->setDefaultCurrency(Agency::DEFAULT_CURRENCY);
        $agency->setSupportedCurrencies([Agency::DEFAULT_CURRENCY, 'USD']);
        $agency->setType(Agency::TYPE_ROAD);
        $agency->setStatus(Agency::STATUS_ACTIVE);
        $agency->setCreatedAt(new \DateTimeImmutable('now'));
        $this->em->persist($agency);
        $this->em->flush();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($agency->getName());
        $user->setPhone($agency->getPhone());
        $user->setProfile($profile);
        $user->setPersonType(UserProxyIntertace::PERSON_PARTNER);
        $user->setHolderId($agency->getId());
        $user->setHolderType(EntityType::AGENCY);
        $user->setPlainPassword($password);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setCreatedAt(new \DateTimeImmutable('now'));
        $this->em->persist($user);
        $this->em->flush();

        $agency->setUserId($user->getId());
        $this->em->flush();

        $transport = new AgencyTransport();
        $transport->setAgency($agency);
        $transport->setLabel(sprintf('%s Bus', $label));
        $transport->setKind(AgencyTransport::KIND_BUS);
        $transport->setPlateNumber(sprintf('T%s', strtoupper($this->suffix . substr($label, 0, 2))));
        $transport->setCapacity($capacity);
        $transport->setStatus(AgencyTransport::STATUS_ACTIVE);
        $this->em->persist($transport);

        $offer = new AgencyOffer();
        $offer->setAgency($agency);
        $offer->setTransport($transport);
        $offer->setLabel(sprintf('%s Express', $label));
        $offer->setOrigin('Kinshasa');
        $offer->setDestination('Matadi');
        $offer->setTicketPrice(85000);
        $offer->setCurrency(Agency::DEFAULT_CURRENCY);
        $offer->setDepartureTime('06:00');
        $offer->setDurationMinutes(180);
        $offer->setActive(true);
        $this->em->persist($offer);
        $this->em->flush();

        $token = $this->authenticate($email, $password);

        return compact('agency', 'user', 'email', 'password', 'token', 'transport', 'offer');
    }

    protected function authenticate(string $email, string $password): string
    {
        $this->client->request(
            'POST',
            '/api/authentication_token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => $email, 'password' => $password], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful(sprintf('Auth failed for %s: %s', $email, $this->client->getResponse()->getContent()));
        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $payload);

        return $payload['token'];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    protected function api(string $method, string $uri, string $token, ?array $body = null, int $expectedStatus = 200): array
    {
        $contentType = 'PATCH' === strtoupper($method)
            ? 'application/merge-patch+json'
            : 'application/json';

        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => $contentType,
            'HTTP_ACCEPT' => 'application/json',
        ];

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        $content = $this->client->getResponse()->getContent() ?: '{}';
        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame(
            $expectedStatus,
            $status,
            sprintf('%s %s expected %d got %d: %s', $method, $uri, $expectedStatus, $status, $content)
        );

        if ('' === $content || 'null' === $content) {
            return [];
        }

        $decoded = json_decode($content, true);
        self::assertIsArray($decoded, sprintf('Invalid JSON for %s %s: %s', $method, $uri, $content));

        return $decoded;
    }

    protected function ensureRoutierTariff(): void
    {
        $gopass = $this->em->getRepository(GoPass::class)->findOneBy(['code' => 'ROUTIER']);
        if (null === $gopass) {
            $gopass = new GoPass();
            $gopass->setCode('ROUTIER');
            $gopass->setLabel('Pass routier OkapiPass');
            $gopass->setTransportType(GoPass::TRANSPORT_ROUTIER);
            $gopass->setPrice(3000);
            $gopass->setCurrency('CDF');
            $gopass->setActive(true);
            $this->em->persist($gopass);
            $this->em->flush();
        }
    }

    protected function seedIssuedPass(string $ref, string $holder = 'Kabongo Jean'): void
    {
        $existing = $this->em->getRepository(IssuedOkapiPass::class)->findOneBy(['reference' => strtoupper($ref)]);
        if (null !== $existing) {
            return;
        }

        $pass = new IssuedOkapiPass();
        $pass->setReference($ref);
        $pass->setHolderName($holder);
        $pass->setStatus(IssuedOkapiPass::STATUS_ACTIVE);
        $this->em->persist($pass);
        $this->em->flush();
    }

    protected function travelDate(string $offset = '+3 days'): string
    {
        return (new \DateTimeImmutable($offset))->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function extractId(array $payload, string $key = 'id'): ?string
    {
        if (isset($payload[$key]) && \is_string($payload[$key])) {
            return $payload[$key];
        }
        if (isset($payload['booking'][$key]) && \is_string($payload['booking'][$key])) {
            return $payload['booking'][$key];
        }
        if (isset($payload['ticket'][$key]) && \is_string($payload['ticket'][$key])) {
            return $payload['ticket'][$key];
        }

        return null;
    }
}
