<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Auth\RequestOtpDto;
use App\Dto\Auth\VerifyOtpDto;
use App\State\Auth\RequestOtpProcessor;
use App\State\Auth\VerifyOtpProcessor;

#[ApiResource(
    shortName: 'TravelerOtp',
    operations: [
        new Post(
            uriTemplate: '/public/auth/otp/request',
            security: 'true',
            input: RequestOtpDto::class,
            output: TravelerOtpResource::class,
            processor: RequestOtpProcessor::class,
            read: false,
            status: 200,
        ),
        new Post(
            uriTemplate: '/public/auth/otp/verify',
            security: 'true',
            input: VerifyOtpDto::class,
            output: TravelerOtpResource::class,
            processor: VerifyOtpProcessor::class,
            read: false,
            status: 200,
        ),
    ]
)]
final class TravelerOtpResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $phone = null,
        public ?string $expiresAt = null,
        public ?string $token = null,
        public ?string $userId = null,
        public ?string $debugCode = null,
    ) {
    }
}
