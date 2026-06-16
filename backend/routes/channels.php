<?php

declare(strict_types=1);

use App\Models\Diagram;
use App\Services\DiagramSharingService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

$diagramPresenceUser = function ($user): array {
    $colors = ['#E53935', '#D81B60', '#8E24AA', '#3949AB', '#1E88E5', '#00ACC1', '#43A047', '#FB8C00'];
    $color = $colors[$user->getAuthIdentifier() % count($colors)];

    return [
        'id' => (string) $user->getAuthIdentifier(),
        'name' => $user->email,
        'color' => $color,
    ];
};

Broadcast::channel('diagram.{shareToken}', function ($user, string $shareToken) use ($diagramPresenceUser) {
    $diagram = Diagram::where('share_token', $shareToken)->first();

    if (! $diagram || ! app(DiagramSharingService::class)->canRead($diagram, $user)) {
        return false;
    }

    return $diagramPresenceUser($user);
});

Broadcast::channel('diagram.{shareToken}.writers', function ($user, string $shareToken) use ($diagramPresenceUser) {
    $diagram = Diagram::where('share_token', $shareToken)->first();

    if (! $diagram || ! app(DiagramSharingService::class)->canWrite($diagram, $user)) {
        return false;
    }

    return $diagramPresenceUser($user);
});
