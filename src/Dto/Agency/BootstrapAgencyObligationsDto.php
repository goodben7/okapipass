<?php

namespace App\Dto\Agency;

use Symfony\Component\Validator\Constraints as Assert;

final class BootstrapAgencyObligationsDto
{
    public function __construct(
        /**
         * Base date for computing first due dates (YYYY-MM-DD). Defaults to today.
         */
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $fromDate = null,

        /**
         * Optional list of type codes to bootstrap. Empty = all active types.
         *
         * @var list<string>|null
         */
        public ?array $typeCodes = null,
    ) {
    }
}
