<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\DiagramAccess;

readonly class ShareSettingsDTO
{
    public function __construct(
        public ?DiagramAccess $access = null,
        public ?bool $requireApproval = null,
        public ?bool $library = null,
    ) {}
}
