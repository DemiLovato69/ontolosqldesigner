<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use App\Models\Diagram;
use App\Models\User;

/**
 * Read-only Foundry Platform operations scoped through an ontology diagram.
 *
 * Resolution: authenticated user + ontology diagram + diagram host -> that
 * user's Foundry connection for the host -> Node SDK bridge.
 */
class FoundryPlatformService
{
    public function __construct(
        private readonly FoundryConnectionService $connections,
        private readonly FoundryRuntimeClient $runtime,
        private readonly FoundryHostConfigService $hosts,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function listSpaces(User $user, Diagram $diagram, array $params = []): array
    {
        return $this->call($user, $diagram, 'listSpaces', $params);
    }

    /**
     * List the children (projects/folders/datasets) of a space, project, or folder.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function listFolderChildren(User $user, Diagram $diagram, string $folderRid, array $params = []): array
    {
        return $this->call($user, $diagram, 'listFolderChildren', array_merge($params, ['folderRid' => $folderRid]));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function listOntologies(User $user, Diagram $diagram): array
    {
        return $this->call($user, $diagram, 'listOntologies', []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function listDatasets(User $user, Diagram $diagram, array $params = []): array
    {
        return $this->call($user, $diagram, 'listDatasets', $params);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function getDataset(User $user, Diagram $diagram, string $datasetRid): array
    {
        return $this->call($user, $diagram, 'getDataset', ['datasetRid' => $datasetRid]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function getDatasetSchema(User $user, Diagram $diagram, string $datasetRid, ?string $branch = null): array
    {
        return $this->call($user, $diagram, 'getDatasetSchema', array_filter([
            'datasetRid' => $datasetRid,
            'branch' => $branch,
        ], static fn ($value): bool => $value !== null));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function listFiles(User $user, Diagram $diagram, string $datasetRid, array $params = []): array
    {
        return $this->call($user, $diagram, 'listFiles', array_merge($params, ['datasetRid' => $datasetRid]));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function getFile(User $user, Diagram $diagram, string $datasetRid, string $filePath): array
    {
        return $this->call($user, $diagram, 'getFile', [
            'datasetRid' => $datasetRid,
            'filePath' => $filePath,
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function search(User $user, Diagram $diagram, string $query, array $params = []): array
    {
        return $this->call($user, $diagram, 'search', array_merge($params, ['query' => $query]));
    }

    /**
     * Verify a raw access token against a host by resolving the current Foundry
     * user. Used when connecting via a pasted token.
     *
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function whoami(string $hostUrl, string $accessToken): array
    {
        $host = $this->hosts->normalize($hostUrl);

        return $this->runtime->run('whoami', $host, $accessToken, []);
    }

    /**
     * Ensure the diagram is an ontology diagram with a configured host and
     * return the normalized host URL.
     *
     * @throws FoundryException
     */
    public function requireDiagramHost(Diagram $diagram): string
    {
        if (! $diagram->isOntology()) {
            throw FoundryException::diagramNotOntology();
        }

        $host = $diagram->foundryConfig?->host_url;
        if (! is_string($host) || $host === '') {
            throw FoundryException::hostNotSet();
        }

        return $this->hosts->normalize($host);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    private function call(User $user, Diagram $diagram, string $operation, array $params): array
    {
        $host = $this->requireDiagramHost($diagram);
        $accessToken = $this->connections->freshAccessToken($user, $host);

        return $this->runtime->run($operation, $host, $accessToken, $params);
    }
}
