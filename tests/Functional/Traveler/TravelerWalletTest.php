<?php

namespace App\Tests\Functional\Traveler;

use App\Entity\User;
use App\Entity\WalletLedger;
use App\Manager\TravelerWalletManager;
use App\Tests\Functional\Agency\AgencyApiTestCase;

final class TravelerWalletTest extends AgencyApiTestCase
{
    public function testGetWalletAndDebitViaManager(): void
    {
        $phone = '+2438100'.random_int(100000, 999999);

        $otp = $this->publicApi('POST', '/api/public/auth/otp/request', ['phone' => $phone], 200);
        $verified = $this->publicApi('POST', '/api/public/auth/otp/verify', [
            'phone' => $phone,
            'code' => $otp['debugCode'],
        ], 200);
        $token = (string) $verified['token'];

        $wallet = $this->api('GET', '/api/traveler/wallet', $token, null, 200);
        self::assertSame(0, $wallet['balance'] ?? null);
        self::assertNotEmpty($wallet['id'] ?? null);

        /** @var TravelerWalletManager $manager */
        $manager = static::getContainer()->get(TravelerWalletManager::class);
        $user = $this->em->getRepository(User::class)->find($verified['userId'] ?? null)
            ?? $this->em->getRepository(User::class)->findOneBy(['phone' => $phone]);
        self::assertInstanceOf(User::class, $user);

        $topup = $manager->createTopup($user, 5000, preg_replace('/\D+/', '', $phone) ?? $phone);
        self::assertSame('PENDING', $topup->getStatus());
        $manager->fulfillTopup($topup);

        $manager->debit($user, 1500, 'test-debit-1', 'Test purchase');
        $ledger = $this->em->getRepository(WalletLedger::class)->findOneBy(['reference' => 'test-debit-1']);
        self::assertInstanceOf(WalletLedger::class, $ledger);
        self::assertSame(WalletLedger::TYPE_DEBIT, $ledger->getType());

        $walletAfter = $this->api('GET', '/api/traveler/wallet', $token, null, 200);
        self::assertSame(3500, $walletAfter['balance'] ?? null);

        $entries = $this->api('GET', '/api/traveler/wallet/ledger', $token, null, 200);
        $member = $entries['member'] ?? $entries['hydra:member'] ?? (array_is_list($entries) ? $entries : []);
        self::assertNotEmpty($member);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function publicApi(string $method, string $uri, ?array $body, int $expectedStatus): array
    {
        $this->client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );
        $content = $this->client->getResponse()->getContent() ?: '{}';
        $status = $this->client->getResponse()->getStatusCode();
        self::assertSame($expectedStatus, $status, sprintf('%s %s: %s', $method, $uri, $content));
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
