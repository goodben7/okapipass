<?php

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TravelerOtpResource;
use App\Dto\Auth\VerifyOtpDto;
use App\Service\Auth\TravelerOtpService;

/** @implements ProcessorInterface<VerifyOtpDto, TravelerOtpResource> */
final class VerifyOtpProcessor implements ProcessorInterface
{
    public function __construct(private TravelerOtpService $otp)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerOtpResource
    {
        \assert($data instanceof VerifyOtpDto);
        $result = $this->otp->verify((string) $data->phone, (string) $data->code);

        return new TravelerOtpResource(
            id: 'otp-verify',
            phone: $result['phone'],
            token: $result['token'],
            userId: $result['userId'],
        );
    }
}
