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
use Illuminate\Queue\SerializesModels;
use Throwable;

class ImportDiagramSchemaJob implements ShouldQueue
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

        $this->diagram->import_status = ImportStatus::PROCESSING;
        $this->diagram->save();

        try {
            $this->diagram->schema        = json_decode($service->createSchema($this->diagram->script), true);
            $this->diagram->import_status = ImportStatus::DONE;
            $this->diagram->import_error  = null;
            $this->diagram->save();
        } catch (Throwable $e) {
            $this->diagram->import_status = ImportStatus::FAILED;
            $this->diagram->import_error  = $e->getMessage();
            $this->diagram->save();
        }
    }
}
