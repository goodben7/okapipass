<?php

namespace App\Domain\Agency;

/**
 * Hard limits for FPT CSV import (sync endpoint).
 */
final class DeclarationCsvLimits
{
    public const int MAX_CONTENT_BYTES = 2_097_152; // 2 MiB (aligned with file upload)

    public const int MAX_ROWS = 5_000;

    /** Sliding window: max imports per authenticated partner. */
    public const int RATE_LIMIT = 20;

    public const int RATE_WINDOW_SECONDS = 60;
}
