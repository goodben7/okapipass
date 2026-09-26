<?php

namespace App\Tests\Unit\Service\Agency;

use App\Exception\UnprocessableEntityException;
use App\Service\Agency\DreamDigitalSmsSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DreamDigitalSmsSenderTest extends TestCase
{
    public function testSendPostsTransactionalSmsAndReturnsMessageId(): void
    {
        $captured = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = [
                'method' => $method,
                'url' => $url,
                'body' => json_decode($options['body'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR),
            ];

            return new MockResponse(json_encode([
                'message_id' => 4125,
                'status' => 'S',
                'remarks' => 'Message Submitted Successfully',
                'uid' => 'xyz',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $sender = new DreamDigitalSmsSender(
            $client,
            new NullLogger(),
            'https://api2.dream-digital.info',
            'API_TEST',
            'secret',
            'TEST-SMS',
            'T',
            'T',
        );

        $id = $sender->send('+243823783066', 'TESTAPI');

        self::assertSame('DD-4125', $id);
        self::assertSame('POST', $captured['method']);
        self::assertSame('https://api2.dream-digital.info/api/SendSMS', $captured['url']);
        self::assertSame('243823783066', $captured['body']['phonenumber']);
        self::assertSame('TESTAPI', $captured['body']['textmessage']);
        self::assertSame('T', $captured['body']['sms_type']);
        self::assertSame('TEST-SMS', $captured['body']['sender_id']);
        self::assertArrayHasKey('uid', $captured['body']);
    }

    public function testSendFailsWhenGatewayReturnsF(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'message_id' => 0,
            'status' => 'F',
            'remarks' => 'Invalid Sender ID',
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200]));

        $sender = new DreamDigitalSmsSender(
            $client,
            new NullLogger(),
            'https://api2.dream-digital.info',
            'API_TEST',
            'secret',
            'BAD',
        );

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('Invalid Sender ID');
        $sender->send('243823783066', 'x');
    }

    public function testSendFailsWhenNotConfigured(): void
    {
        $sender = new DreamDigitalSmsSender(
            new MockHttpClient(),
            new NullLogger(),
            '',
            '',
            '',
            'TEST-SMS',
        );

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('not configured');
        $sender->send('+243823783066', 'x');
    }
}
