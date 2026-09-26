<?php

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TravelerOtpResource;
use App\Dto\Auth\RequestOtpDto;
use App\Service\Auth\TravelerOtpService;

/** @implements ProcessorInterface<RequestOtpDto, TravelerOtpResource> */
final class RequestOtpProcessor implements ProcessorInterface
{
    public function __construct(private TravelerOtpService $otp)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerOtpResource
    {
        \assert($data instanceof RequestOtpDto);
        $result = $this->otp->request((string) $data->phone);

        return new TravelerOtpResource(
            id: 'otp-request',
            phone: $result['phone'],
            expiresAt: $result['expiresAt'],
            debugCode: $result['debugCode'] ?? null,
        );
    }
}
