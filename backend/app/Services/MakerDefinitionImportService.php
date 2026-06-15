<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidSchemaException;
use Symfony\Component\Process\Process;

class MakerDefinitionImportService
{
    /** @return array<string, mixed> */
    public function convert(string $module): array
    {
        $script = (string) config('services.maker_import.script');
        $node = (string) config('services.maker_import.node', 'node');
        if ($script === '' || ! is_file($script)) {
            throw new InvalidSchemaException('Maker definition import runtime is not installed.');
        }

        $process = new Process([$node, $script]);
        $process->setInput($module);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: 'Maker definition import failed.';
            throw new InvalidSchemaException($message);
        }

        $decoded = json_decode($process->getOutput(), true);
        if (! is_array($decoded)) {
            throw new InvalidSchemaException('Maker definition importer returned invalid ontology JSON.');
        }

        $this->validateOntology($decoded);

        return $decoded;
    }

    /** @param array<string, mixed> $ontology */
    private function validateOntology(array $ontology): void
    {
        $objectTypes = $ontology['objectTypes'] ?? null;
        $relations = $ontology['relations'] ?? null;
        $valueTypes = $ontology['valueTypes'] ?? null;
        if (! is_array($objectTypes) || ! is_array($relations) || ! is_array($valueTypes)) {
            throw new InvalidSchemaException('Maker definition importer returned an incomplete ontology.');
        }

        $objectRids = [];
        $propertyRids = [];
        $propertiesByObject = [];
        foreach ($objectTypes as $object) {
            if (! is_array($object)) {
                throw new InvalidSchemaException('Maker definition importer returned an invalid object type.');
            }
            $objectRid = $this->requiredIdentifier($object, 'rid', 'object type');
            if (isset($objectRids[$objectRid])) {
                throw new InvalidSchemaException("Maker definition import contains duplicate object identifier: {$objectRid}.");
            }
            $objectRids[$objectRid] = true;

            $properties = $object['properties'] ?? null;
            if (! is_array($properties)) {
                throw new InvalidSchemaException("Maker object {$objectRid} has invalid properties.");
            }
            $localProperties = [];
            foreach ($properties as $property) {
                if (! is_array($property)) {
                    throw new InvalidSchemaException("Maker object {$objectRid} has an invalid property.");
                }
                $propertyRid = $this->requiredIdentifier($property, 'rid', "property on {$objectRid}");
                $apiName = $this->requiredIdentifier($property, 'apiName', "property on {$objectRid}");
                if (isset($propertyRids[$propertyRid])) {
                    throw new InvalidSchemaException("Maker definition import contains duplicate property identifier: {$propertyRid}.");
                }
                if (isset($localProperties[$apiName])) {
                    throw new InvalidSchemaException("Maker object {$objectRid} contains duplicate property: {$apiName}.");
                }
                $propertyRids[$propertyRid] = true;
                $localProperties[$apiName] = true;
            }
            $propertiesByObject[$objectRid] = $localProperties;

            $titlePropertyId = $object['titlePropertyId'] ?? null;
            if (is_string($titlePropertyId) && $titlePropertyId !== '' && ! isset($localProperties[$titlePropertyId])) {
                throw new InvalidSchemaException("Maker object {$objectRid} references unknown title property: {$titlePropertyId}.");
            }
            foreach (is_array($object['primaryKeys'] ?? null) ? $object['primaryKeys'] : [] as $primaryKey) {
                if (! is_string($primaryKey) || ! isset($localProperties[$primaryKey])) {
                    throw new InvalidSchemaException("Maker object {$objectRid} references an unknown primary key.");
                }
            }
        }

        $relationRids = [];
        foreach ($relations as $relation) {
            if (! is_array($relation)) {
                throw new InvalidSchemaException('Maker definition importer returned an invalid relation.');
            }
            $relationRid = $this->requiredIdentifier($relation, 'rid', 'relation');
            if (isset($relationRids[$relationRid])) {
                throw new InvalidSchemaException("Maker definition import contains duplicate relation identifier: {$relationRid}.");
            }
            $relationRids[$relationRid] = true;
            $definition = is_array($relation['definition'] ?? null) ? $relation['definition'] : [];
            $type = $definition['type'] ?? null;

            if ($type === 'oneToMany') {
                $link = is_array($definition['oneToMany'] ?? null) ? $definition['oneToMany'] : [];
                $this->validateObjectReference($objectRids, $link['objectTypeRidOneSide'] ?? null, $relationRid);
                $manyRid = $this->validateObjectReference($objectRids, $link['objectTypeRidManySide'] ?? null, $relationRid);
                $mapping = is_array($link['oneSidePrimaryKeyToManySidePropertyMapping'] ?? null)
                    ? $link['oneSidePrimaryKeyToManySidePropertyMapping']
                    : [];
                if ($mapping === []) {
                    throw new InvalidSchemaException("Maker relation {$relationRid} has no property mapping.");
                }
                foreach ($mapping as $sourcePropertyRid => $targetPropertyRid) {
                    if (! is_string($sourcePropertyRid)
                        || ! is_string($targetPropertyRid)
                        || ! isset($propertyRids[$sourcePropertyRid], $propertyRids[$targetPropertyRid])) {
                        throw new InvalidSchemaException("Maker relation {$relationRid} references an unknown property.");
                    }
                }
                $foreignKey = $link['manySideForeignKeyPropertyId'] ?? null;
                if (! is_string($foreignKey) || ! isset($propertiesByObject[$manyRid][$foreignKey])) {
                    throw new InvalidSchemaException("Maker relation {$relationRid} references an unknown foreign key property.");
                }
            } elseif ($type === 'manyToMany') {
                $link = is_array($definition['manyToMany'] ?? null) ? $definition['manyToMany'] : [];
                $this->validateObjectReference($objectRids, $link['objectTypeRidA'] ?? null, $relationRid);
                $this->validateObjectReference($objectRids, $link['objectTypeRidB'] ?? null, $relationRid);
            } else {
                throw new InvalidSchemaException("Maker relation {$relationRid} has unsupported type.");
            }
        }

        $valueTypeNames = [];
        foreach ($valueTypes as $valueType) {
            if (! is_array($valueType)) {
                throw new InvalidSchemaException('Maker definition importer returned an invalid value type.');
            }
            $apiName = $this->requiredIdentifier($valueType, 'apiName', 'value type');
            if (isset($valueTypeNames[$apiName])) {
                throw new InvalidSchemaException("Maker definition import contains duplicate value type: {$apiName}.");
            }
            $valueTypeNames[$apiName] = true;
        }
    }

    /** @param array<string, mixed> $record */
    private function requiredIdentifier(array $record, string $key, string $entity): string
    {
        $value = $record[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidSchemaException("Maker definition importer returned a {$entity} without a valid {$key}.");
        }

        return $value;
    }

    /**
     * @param array<string, true> $objectRids
     */
    private function validateObjectReference(array $objectRids, mixed $objectRid, string $relationRid): string
    {
        if (! is_string($objectRid) || ! isset($objectRids[$objectRid])) {
            throw new InvalidSchemaException("Maker relation {$relationRid} references an unknown object type.");
        }

        return $objectRid;
    }
}
