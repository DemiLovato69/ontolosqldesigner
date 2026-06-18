<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\OAuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\RealtimeConfigController;
use App\Http\Controllers\DiagramChangelogController;
use App\Http\Controllers\DiagramController;
use App\Http\Controllers\ReviewController;
use App\Models\Diagram;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::get('/auth/oauth/google/authorize', [OAuthController::class, 'authorize']);
        Route::get('/auth/oauth/google/callback', [OAuthController::class, 'callback']);
        Route::post('/auth/oauth/google/token', [OAuthController::class, 'token']);
    });

    Route::middleware(['auth:sanctum', 'track.seen'])->group(function (): void {
        Route::get('/auth/me', [TokenController::class, 'me'])->middleware('abilities:desktop');
        Route::get('/auth/tokens', [TokenController::class, 'index'])->middleware('abilities:desktop,tokens:manage');
        Route::delete('/auth/token/current', [TokenController::class, 'destroyCurrent'])->middleware('abilities:desktop');
        Route::delete('/auth/tokens/{token}', [TokenController::class, 'destroy'])->middleware('abilities:desktop,tokens:manage');
        Route::post('/auth/email/resend', [\App\Http\Controllers\AuthController::class, 'resendVerification']);

        Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])->middleware('abilities:desktop,presence:read');
        Route::get('/realtime/config', [RealtimeConfigController::class, 'show'])->middleware('abilities:desktop,presence:read');

        Route::get('/shared/diagrams/{token}', [DiagramController::class, 'showByToken'])->middleware('abilities:desktop,diagrams:read');
        Route::patch('/shared/diagrams/{token}', [DiagramController::class, 'saveByToken'])->middleware('abilities:desktop,diagrams:write');
        Route::post('/shared/diagrams/{token}/duplicate', [DiagramController::class, 'duplicateByToken'])->middleware('abilities:desktop,diagrams:write');

        Route::get('/review', [ReviewController::class, 'check']);
        Route::middleware('throttle:5,1')->post('/review', [ReviewController::class, 'store']);

        Route::group(['prefix' => 'diagrams', 'middleware' => ['verified']], function (): void {
            Route::get('/', [DiagramController::class, 'index'])->middleware('abilities:desktop,diagrams:read');
            Route::get('/dashboard', [DiagramController::class, 'dashboard'])->middleware('abilities:desktop,diagrams:read');
            Route::get('/share-users/search', [DiagramController::class, 'searchShareUsers'])->middleware('abilities:desktop,sharing:write');

            Route::post('/', [DiagramController::class, 'store'])->middleware('abilities:desktop,diagrams:write');
            Route::get('/{diagram}', [DiagramController::class, 'show'])->middleware('abilities:desktop,diagrams:read');
            Route::put('/{diagram}', [DiagramController::class, 'update'])->middleware('abilities:desktop,diagrams:write');
            Route::patch('/{diagram}', [DiagramController::class, 'update'])->middleware('abilities:desktop,diagrams:write');
            Route::delete('/{diagram}', [DiagramController::class, 'destroy'])->middleware('abilities:desktop,diagrams:delete');

            Route::post('/{diagram}/share', [DiagramController::class, 'share'])->middleware('abilities:desktop,sharing:write');
            Route::patch('/{diagram}/share', [DiagramController::class, 'updateShareAccess'])->middleware('abilities:desktop,sharing:write');
            Route::delete('/{diagram}/share', [DiagramController::class, 'unshare'])->middleware('abilities:desktop,sharing:write');
            Route::get('/{diagram}/invites', [DiagramController::class, 'getInvites'])->middleware('abilities:desktop,sharing:write');
            Route::put('/{diagram}/invites', [DiagramController::class, 'updateInvites'])->middleware('abilities:desktop,sharing:write');
            Route::get('/{diagram}/visitors', [DiagramController::class, 'getVisitors'])->middleware('abilities:desktop,sharing:write');
            Route::post('/{diagram}/visitors/{visitor}/approve', [DiagramController::class, 'approveVisitor'])->middleware('abilities:desktop,sharing:write');
            Route::patch('/{diagram}/visitors/{visitor}', [DiagramController::class, 'updateVisitorAccess'])->middleware('abilities:desktop,sharing:write');

            Route::post('/{diagram}/imports/{format}', function (Diagram $diagram, string $format, Request $request) {
                return app(DiagramController::class)->importFormat($format, $diagram, $request);
            })->whereIn('format', ['sql', 'ontology-json', 'backup-json', 'maker-mts'])->middleware('abilities:desktop,imports:write');
            Route::post('/{diagram}/imports', [DiagramController::class, 'createImportUpload'])->middleware('abilities:desktop,imports:write');
            Route::put('/{diagram}/imports/{import}/chunks/{index}', [DiagramController::class, 'uploadImportChunk'])
                ->whereNumber('index')
                ->middleware('abilities:desktop,imports:write');
            Route::post('/{diagram}/imports/{import}/complete', [DiagramController::class, 'completeImportUpload'])->middleware('abilities:desktop,imports:write');
            Route::get('/{diagram}/imports/status', [DiagramController::class, 'importStatus'])->middleware('abilities:desktop,imports:write');

            Route::post('/{diagram}/exports', [DiagramController::class, 'export'])->middleware('abilities:desktop,exports:read');
            Route::get('/{diagram}/exports/status', [DiagramController::class, 'exportStatus'])->middleware('abilities:desktop,exports:read');
            Route::get('/{diagram}/exports/backup-json', [DiagramController::class, 'exportJson'])->middleware('abilities:desktop,exports:read');
            Route::get('/{diagram}/exports/migration', [DiagramController::class, 'exportMigration'])->middleware('abilities:desktop,exports:read');
            Route::get('/{diagram}/exports/ontology', [DiagramController::class, 'exportOntology'])->middleware('abilities:desktop,exports:read');

            Route::get('/{diagram}/changelog', [DiagramChangelogController::class, 'index'])->middleware('abilities:desktop,changelog:read');
            Route::post('/{diagram}/changelog', [DiagramChangelogController::class, 'store'])->middleware('abilities:desktop,changelog:write');
        });
    });
});
