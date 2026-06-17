<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\DbType;
use App\Enums\DiagramAccess;

readonly class UpdateDiagramDTO
{
    /**
     * @param array<int, mixed>|null $schema
     * @param list<array<string, mixed>>|null $valueTypes
     * @param list<array<string, mixed>>|null $interfaces
     * @param list<array<string, mixed>>|null $interfaceLinkConstraints
     * @param list<array<string, mixed>>|null $customActions
     * @param list<array<string, mixed>>|null $sharedPropertyTypes
     */
    public function __construct(
        public ?string $name = null,
        public ?DbType $dbType = null,
        public ?DiagramAccess $shareAccess = null,
        public ?bool $library = null,
        public ?array $schema = null,
        public ?array $valueTypes = null,
        public ?array $interfaces = null,
        public ?array $interfaceLinkConstraints = null,
        public ?array $customActions = null,
        public ?array $sharedPropertyTypes = null,
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
        if ($this->valueTypes !== null) {
            $data['value_types'] = $this->valueTypes;
        }
        if ($this->interfaces !== null) {
            $data['interfaces'] = $this->interfaces;
        }
        if ($this->interfaceLinkConstraints !== null) {
            $data['interface_link_constraints'] = $this->interfaceLinkConstraints;
        }
        if ($this->customActions !== null) {
            $data['custom_actions'] = $this->customActions;
        }
        if ($this->sharedPropertyTypes !== null) {
            $data['shared_property_types'] = $this->sharedPropertyTypes;
        }

        return $data;
    }
}
