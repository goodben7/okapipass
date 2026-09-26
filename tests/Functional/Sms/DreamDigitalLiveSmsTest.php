<?php

namespace App\Tests\Functional\Sms;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Live Dream Digital smoke test — skipped unless explicitly enabled.
 *
 *   DREAM_DIGITAL_LIVE_TEST=1 php bin/phpunit tests/Functional/Sms/DreamDigitalLiveSmsTest.php
 *
 * Prefer the console command for manual checks:
 *   php bin/console okapi:test-sms +243823783066
 */
final class DreamDigitalLiveSmsTest extends KernelTestCase
{
    public function testLiveSendWhenEnabled(): void
    {
        if ('1' !== ($_ENV['DREAM_DIGITAL_LIVE_TEST'] ?? $_SERVER['DREAM_DIGITAL_LIVE_TEST'] ?? '0')) {
            self::markTestSkipped('Set DREAM_DIGITAL_LIVE_TEST=1 to hit the real Dream Digital API.');
        }
        if ('1' !== ($_ENV['DREAM_DIGITAL_SMS_ENABLED'] ?? $_SERVER['DREAM_DIGITAL_SMS_ENABLED'] ?? '0')) {
            self::markTestSkipped('DREAM_DIGITAL_SMS_ENABLED must be 1.');
        }

        $phone = $_ENV['DREAM_DIGITAL_LIVE_PHONE'] ?? $_SERVER['DREAM_DIGITAL_LIVE_PHONE'] ?? '';
        if ('' === trim($phone)) {
            self::markTestSkipped('Set DREAM_DIGITAL_LIVE_PHONE=+243… for the live SMS test.');
        }

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('okapi:test-sms');
        $tester = new CommandTester($command);
        $status = $tester->execute([
            'phone' => $phone,
            '--message' => 'OkapiPass LIVE TEST '.date('H:i:s'),
        ]);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringContainsString('smsMessageId=', $tester->getDisplay());
    }
}
