<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TravelerPushTokenResource;
use App\Dto\Traveler\RegisterTravelerPushTokenDto;
use App\Provider\Traveler\TravelerMeProvider;
use Doctrine\ORM\EntityManagerInterface;

/** @implements ProcessorInterface<RegisterTravelerPushTokenDto, TravelerPushTokenResource> */
final class RegisterTravelerPushTokenProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerMeProvider $meProvider,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPushTokenResource
    {
        \assert($data instanceof RegisterTravelerPushTokenDto);
        $user = $this->meProvider->requireTraveler();
        $prefs = $user->getPreferences() ?? [];
        $tokens = \is_array($prefs['pushTokens'] ?? null) ? $prefs['pushTokens'] : [];
        $token = trim((string) $data->deviceToken);
        if (!\in_array($token, $tokens, true)) {
            $tokens[] = $token;
        }
        $prefs['pushTokens'] = array_values($tokens);
        if (null !== $data->platform && '' !== trim($data->platform)) {
            $prefs['pushPlatform'] = trim($data->platform);
        }
        $user->setPreferences($prefs);
        $this->em->flush();

        return new TravelerPushTokenResource(
            id: 'push',
            deviceToken: $token,
            platform: $data->platform,
        );
    }
}
