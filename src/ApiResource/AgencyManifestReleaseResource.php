<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;

final class AgencyManifestReleaseResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public int $releasedCount,
        public string $offerId,
        public string $travelDate,
    ) {
    }
}
