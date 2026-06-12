<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DbType;
use App\Enums\ExportStatus;
use App\Models\Diagram;
use App\Services\DiagramSqlService;
use App\Services\OntologyMakerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExportDiagramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 15;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(private Diagram $diagram)
    {
        $this->onConnection('database');
        $this->onQueue('diagrams');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->diagram->id))
                ->releaseAfter(30)
                ->expireAfter(330),
        ];
    }

    public function handle(DiagramSqlService $service, OntologyMakerService $ontologyMakerService): void
    {
        ini_set('memory_limit', '512M');

        $this->diagram->export_status = ExportStatus::PROCESSING;
        $this->diagram->save();
        $this->diagram->refresh();

        $schemaJson = json_encode($this->diagram->schema);
        $dbType = $this->diagram->db_type ?? DbType::MYSQL;
        $script = $dbType === DbType::ONTOLOGY
            ? $ontologyMakerService->createModule($schemaJson, $this->diagram->value_types ?? [])
            : $service->createScript($schemaJson, $dbType->value);

        $this->diagram->script = $script;
        $this->diagram->export_json = $service->createJson(
            $schemaJson,
            $this->diagram->value_types ?? [],
            $dbType->value,
            $this->diagram->name
        );
        $this->diagram->export_status = ExportStatus::DONE;
        $this->diagram->export_error = null;
        $this->diagram->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->diagram->export_status = ExportStatus::FAILED;
        $this->diagram->export_error = $exception->getMessage();
        $this->diagram->save();

        Log::error('ExportDiagramJob failed', [
            'diagram_id' => $this->diagram->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
