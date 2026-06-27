<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Calls a Foundry AIP OpenAI-compatible LLM proxy endpoint directly with the
 * requesting user's Foundry access token. The token is sent as a bearer header
 * and is never logged. Responses are returned decoded; HTTP/transport failures
 * are normalized to stable foundry_* error codes.
 */
class FoundryLlmClient
{
    /**
     * @param array<string, mixed> $payload OpenAI chat-completions request body.
     * @return array<string, mixed>
     *
     * @throws FoundryException
     */
    public function chatCompletion(string $hostUrl, string $accessToken, array $payload): array
    {
        $url = rtrim($hostUrl, '/').'/'.ltrim((string) config('foundry.llm.endpoint'), '/');

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('foundry.llm.timeout', 60))
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            throw FoundryException::upstreamUnavailable('Could not reach the Foundry model endpoint.');
        }

        if ($response->successful()) {
            $data = $response->json();

            if (! is_array($data)) {
                throw FoundryException::llmInvalidResponse('The model endpoint returned an invalid payload.');
            }

            return $data;
        }

        throw $this->mapError($response->status());
    }

    private function mapError(int $status): FoundryException
    {
        return match (true) {
            $status === 401, $status === 403 => FoundryException::accessDenied('Foundry rejected the model request.'),
            $status === 404 => FoundryException::llmModelNotAllowed('The model endpoint or model was not found on this Foundry host.'),
            $status === 429 => FoundryException::llmRateLimited(),
            default => FoundryException::upstreamUnavailable('The Foundry model request failed.'),
        };
    }
}
