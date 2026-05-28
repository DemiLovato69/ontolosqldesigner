<?php

namespace App\Jobs;

use App\Enums\DbType;
use App\Enums\ExportStatus;
use App\Models\Diagram;
use App\Services\DiagramSqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExportDiagramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;
    public bool $deleteWhenMissingModels = true;

    public function __construct(private Diagram $diagram)
    {
        $this->onQueue('diagrams');
    }

    public function handle(DiagramSqlService $service): void
    {
        ini_set('memory_limit', '512M');

        $this->diagram->export_status = ExportStatus::PROCESSING;
        $this->diagram->save();

        try {
            $schemaJson = json_encode($this->diagram->schema);
            $sqlScript  = $service->createScript($schemaJson, ($this->diagram->db_type ?? DbType::MYSQL)->value);
            $jsonExport = $service->createJson($schemaJson);

            $this->diagram->script        = $sqlScript;
            $this->diagram->export_json   = json_decode($jsonExport, true);
            $this->diagram->export_status = ExportStatus::DONE;
            $this->diagram->export_error  = null;
            $this->diagram->save();
        } catch (Throwable $e) {
            $this->diagram->export_status = ExportStatus::FAILED;
            $this->diagram->export_error  = $e->getMessage();
            $this->diagram->save();
        }
    }
}
