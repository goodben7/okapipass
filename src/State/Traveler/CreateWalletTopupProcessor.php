<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TravelerWalletTopupResource;
use App\Dto\Traveler\CreateWalletTopupDto;
use App\Entity\WalletTopup;
use App\Manager\TravelerWalletManager;
use App\Provider\Traveler\TravelerMeProvider;
use App\Provider\Traveler\TravelerWalletTopupItemProvider;

/** @implements ProcessorInterface<CreateWalletTopupDto, TravelerWalletTopupResource> */
final class CreateWalletTopupProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerWalletManager $wallets,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerWalletTopupResource
    {
        \assert($data instanceof CreateWalletTopupDto);
        $user = $this->travelerMe->requireTraveler();
        $topup = $this->wallets->createTopup(
            $user,
            (int) $data->amount,
            (string) $data->phone,
            $data->method ?? WalletTopup::METHOD_MOBILE_MONEY,
        );

        return TravelerWalletTopupItemProvider::fromEntity($topup);
    }
}
