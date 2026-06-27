<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Domain exception for Foundry integration failures. Carries a stable machine
 * error code and HTTP status so the API surface stays consistent across the
 * web SPA and desktop clients.
 */
class FoundryException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function hostNotConfigured(): self
    {
        return new self(
            'foundry_host_not_configured',
            409,
            'This Foundry host has not been configured by an administrator.',
        );
    }

    public static function hostNotAllowed(string $detail = 'This Foundry host is not allowed.'): self
    {
        return new self('foundry_host_not_allowed', 422, $detail);
    }

    public static function parameterRequired(string $name): self
    {
        return new self('foundry_parameter_required', 422, "The '{$name}' parameter is required.");
    }

    public static function tokenAuthDisabled(): self
    {
        return new self(
            'foundry_token_auth_disabled',
            422,
            'Token-based Foundry connections are disabled. Connect with OAuth instead.',
        );
    }

    public static function diagramNotOntology(): self
    {
        return new self(
            'foundry_diagram_not_ontology',
            422,
            'Foundry integration is only available for ontology diagrams.',
        );
    }

    public static function hostNotSet(): self
    {
        return new self(
            'foundry_host_not_set',
            409,
            'This diagram does not have a Foundry host configured yet.',
        );
    }

    public static function connectionRequired(): self
    {
        return new self(
            'foundry_connection_required',
            409,
            'Connect your Foundry account for this host to continue.',
        );
    }

    public static function connectionExpired(): self
    {
        return new self(
            'foundry_connection_expired',
            409,
            'Your Foundry connection has expired. Reconnect to continue.',
        );
    }

    public static function accessDenied(string $detail = 'Foundry denied access to this resource.'): self
    {
        return new self('foundry_access_denied', 403, $detail);
    }

    public static function resourceNotFound(string $detail = 'The requested Foundry resource was not found.'): self
    {
        return new self('foundry_resource_not_found', 404, $detail);
    }

    public static function rateLimited(): self
    {
        return new self('foundry_rate_limited', 429, 'Foundry rate limit reached. Try again shortly.');
    }

    public static function upstreamUnavailable(string $detail = 'Foundry is currently unavailable.'): self
    {
        return new self('foundry_upstream_unavailable', 502, $detail);
    }

    /**
     * Build an exception from a runtime/upstream error code, defaulting to a
     * generic upstream failure for unknown codes.
     */
    public static function fromCode(string $code, string $message = ''): self
    {
        return match ($code) {
            'foundry_host_not_configured' => self::hostNotConfigured(),
            'foundry_host_not_allowed' => self::hostNotAllowed($message ?: 'This Foundry host is not allowed.'),
            'foundry_connection_required' => self::connectionRequired(),
            'foundry_connection_expired' => self::connectionExpired(),
            'foundry_access_denied' => self::accessDenied($message ?: 'Foundry denied access to this resource.'),
            'foundry_resource_not_found' => self::resourceNotFound($message ?: 'The requested Foundry resource was not found.'),
            'foundry_rate_limited' => self::rateLimited(),
            default => self::upstreamUnavailable($message ?: 'Foundry is currently unavailable.'),
        };
    }

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ], $this->status);
    }
}
