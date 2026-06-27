<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

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
            Log::error('Foundry runtime script missing', ['operation' => $operation, 'script' => $script]);

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
            Log::error('Foundry runtime timed out', ['operation' => $operation, 'host' => $hostUrl]);

            throw FoundryException::upstreamUnavailable('The Foundry request timed out.');
        } catch (ProcessRuntimeException $exception) {
            // The Node process could not be started (e.g. node missing or
            // proc_open disabled). Surface a clean error and log the cause.
            Log::error('Foundry runtime failed to start', [
                'operation' => $operation,
                'node' => $node,
                'script' => $script,
                'error' => $exception->getMessage(),
            ]);

            throw FoundryException::upstreamUnavailable('The Foundry runtime could not be started.');
        }

        $stdout = (string) $process->getOutput();
        $decoded = json_decode($stdout, true);

        if (! is_array($decoded)) {
            $this->logFailure('Foundry runtime returned no JSON', $operation, $hostUrl, $process, $stdout);

            throw FoundryException::upstreamUnavailable('The Foundry runtime returned an invalid response.');
        }

        if (($decoded['ok'] ?? false) === true) {
            $data = $decoded['data'] ?? [];

            return is_array($data) ? $data : ['value' => $data];
        }

        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $code = is_string($error['code'] ?? null) ? $error['code'] : 'foundry_upstream_unavailable';
        $message = is_string($error['message'] ?? null) ? $error['message'] : '';

        // Log upstream/runtime failures server-side so production issues are
        // diagnosable. Access tokens are never part of the envelope or stderr.
        if ($code === 'foundry_upstream_unavailable' || $code === 'foundry_access_denied') {
            $this->logFailure('Foundry runtime error', $operation, $hostUrl, $process, $stdout, $code, $message);
        }

        throw FoundryException::fromCode($code, $message);
    }

    private function logFailure(
        string $label,
        string $operation,
        string $hostUrl,
        Process $process,
        string $stdout,
        ?string $code = null,
        ?string $message = null,
    ): void {
        try {
            Log::error($label, array_filter([
                'operation' => $operation,
                'host' => $hostUrl,
                'code' => $code,
                'message' => $message,
                'exit_code' => $process->getExitCode(),
                'stderr' => $this->tail($process->getErrorOutput()),
                'stdout' => $code === null ? $this->tail($stdout) : null,
            ], static fn ($value): bool => $value !== null && $value !== ''));
        } catch (Throwable) {
            // Never let logging break the request.
        }
    }

    private function tail(string $text, int $limit = 600): string
    {
        $text = trim($text);

        return strlen($text) > $limit ? '…'.substr($text, -$limit) : $text;
    }
}
