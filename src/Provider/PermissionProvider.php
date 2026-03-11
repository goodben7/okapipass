<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PermissionResource;
use App\Manager\PermissionManager;
use App\Model\Permission;

class PermissionProvider implements ProviderInterface
{

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $list = [];
        /** @var Permission $p */
        foreach (PermissionManager::getInstance()->getPermissions() as $p) {
            $list[] = PermissionResource::fromModel($p);
        }

        return $list; 
    }
}
