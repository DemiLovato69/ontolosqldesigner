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
        public ?array $schema = null,
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
            $data['share_access'] = $this->shareAccess;
        }
        if ($this->library !== null) {
            $data['library'] = $this->library;
        }
        if ($this->schema !== null) {
            $data['schema'] = $this->schema;
        }

        return $data;
    }
}
