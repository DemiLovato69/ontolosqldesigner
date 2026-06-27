<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Invokes the Node Foundry Platform SDK bridge. The access token is passed over
 * stdin (never argv/logs) and the runtime returns a normalized JSON envelope.
 */
class FoundryRuntimeClient
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function run(string $operation, string $hostUrl, string $accessToken, array $params = []): array
    {
        $script = (string) config('foundry.runtime.script');
        $node = (string) config('foundry.runtime.node', 'node');
        if ($script === '' || ! is_file($script)) {
            throw FoundryException::upstreamUnavailable('The Foundry runtime is not installed.');
        }

        $payload = json_encode([
            'operation' => $operation,
            'hostUrl' => $hostUrl,
            'accessToken' => $accessToken,
            'params' => (object) $params,
        ], JSON_THROW_ON_ERROR);

        $process = new Process([$node, $script]);
        $process->setInput($payload);
        $process->setTimeout((float) config('foundry.runtime.timeout', 30));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw FoundryException::upstreamUnavailable('The Foundry request timed out.');
        }

        $decoded = json_decode((string) $process->getOutput(), true);

        if (! is_array($decoded)) {
            throw FoundryException::upstreamUnavailable('The Foundry runtime returned an invalid response.');
        }

        if (($decoded['ok'] ?? false) === true) {
            $data = $decoded['data'] ?? [];

            return is_array($data) ? $data : ['value' => $data];
        }

        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $code = is_string($error['code'] ?? null) ? $error['code'] : 'foundry_upstream_unavailable';
        $message = is_string($error['message'] ?? null) ? $error['message'] : '';

        throw FoundryException::fromCode($code, $message);
    }
}
