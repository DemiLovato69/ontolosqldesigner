<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RealtimeConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return $this->success(['data' => [
            'driver' => 'reverb',
            'app_key' => config('broadcasting.connections.reverb.key'),
            'host' => config('reverb.apps.apps.0.options.host'),
            'port' => (int) config('reverb.apps.apps.0.options.port'),
            'scheme' => config('reverb.apps.apps.0.options.scheme'),
            'auth_endpoint' => rtrim((string) config('app.url'), '/').'/api/v1/broadcasting/auth',
            'channels' => [
                'presence' => 'diagram.{share_token}',
                'writers' => 'diagram.{share_token}.writers',
            ],
            'events' => [
                'whispers' => [
                    'cursor-moved',
                    'schema-sync',
                    'value-types-sync',
                    'schema-patch',
                    'diagram-saved',
                ],
                'server' => [
                    '.schema.imported',
                    '.visitor.requested',
                    '.visitor.access.changed',
                ],
            ],
        ]]);
    }
}
