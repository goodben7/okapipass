<?php
namespace App\ApiResource;

use App\Model\Permission;
use App\Provider\PermissionProvider;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    shortName: "Permission",
    operations: [
        new GetCollection(
            provider: PermissionProvider::class,
        )
    ]
)]
class PermissionResource {

    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $role,
        public string $label,
    )
    {

    }

    public static function fromModel(Permission $p): static {
        return new self($p->getPermissionId(), $p->getLabel());
    }
}