<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Diagram;
use App\Services\DiagramSqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportDiagramSchemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(private Diagram $diagram)
    {
        $this->onQueue('diagrams');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->diagram->id)];
    }

    public function handle(DiagramSqlService $service): void
    {
        ini_set('memory_limit', '512M');

        if ($this->diagram->script === null) {
            $this->diagram->import_status = ImportStatus::FAILED;
            $this->diagram->import_error = 'No script to import.';
            $this->diagram->save();

            return;
        }

        $this->diagram->import_status = ImportStatus::PROCESSING;
        $this->diagram->save();

        $this->diagram->schema = json_decode($service->createSchema($this->diagram->script), true);
        $this->diagram->import_status = ImportStatus::DONE;
        $this->diagram->import_error = null;
        $this->diagram->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->diagram->import_status = ImportStatus::FAILED;
        $this->diagram->import_error = $exception->getMessage();
        $this->diagram->save();

        Log::error('ImportDiagramSchemaJob failed', [
            'diagram_id' => $this->diagram->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
