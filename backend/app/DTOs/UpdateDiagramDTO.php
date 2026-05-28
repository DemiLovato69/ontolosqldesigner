<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\DbType;
use App\Enums\DiagramAccess;

readonly class UpdateDiagramDTO
{
    public function __construct(
        public ?string $name = null,
        public ?DbType $dbType = null,
        public ?DiagramAccess $shareAccess = null,
        public ?bool $library = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->dbType !== null) {
            $data['db_type'] = $this->dbType;
        }
        if ($this->shareAccess !== null) {
            $data['share_access'] = $this->shareAccess->value;
        }
        if ($this->library !== null) {
            $data['library'] = $this->library;
        }

        return $data;
    }
}
