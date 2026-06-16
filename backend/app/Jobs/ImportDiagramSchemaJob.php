<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DbType;
use App\Enums\ImportStatus;
use App\Models\Diagram;
use App\Models\DiagramImport;
use App\Services\DiagramSqlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportDiagramSchemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ?string $expectedScriptHash = null;

    public int $timeout = 1800;

    public int $tries = 15;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(private Diagram $diagram, private ?DiagramImport $import = null)
    {
        $this->expectedScriptHash = $import === null && is_string($diagram->script)
            ? hash('sha256', $diagram->script)
            : null;
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

    public function handle(DiagramSqlService $service): void
    {
        ini_set('memory_limit', '3072M');

        $this->diagram->refresh();
        $currentStatus = $this->diagram->import_status;
        if (! in_array($currentStatus, [ImportStatus::PENDING, ImportStatus::PROCESSING], true)) {
            return;
        }

        $import = $this->resolveImport($service);
        if ($import === null) {
            return;
        }

        $this->diagram->import_status = ImportStatus::PROCESSING;
        $this->diagram->save();

        $payload = $service->createImportPayload(
            $import['content'],
            ($this->diagram->db_type ?? DbType::MYSQL)->value,
            $import['format']
        );
        $this->diagram->schema = $payload['schema'];
        $this->diagram->value_types = $payload['value_types'];
        if ($payload['db_type'] !== null) {
            $this->diagram->db_type = $payload['db_type'];
        }
        $this->diagram->import_warnings = $payload['warnings'];
        $this->diagram->import_status = ImportStatus::DONE;
        $this->diagram->import_error = null;
        $this->diagram->save();

        if ($this->import !== null) {
            $this->import->status = DiagramImport::STATUS_DONE;
            $this->import->error = null;
            $this->import->save();

            if (is_string($this->import->path)) {
                Storage::disk($this->import->disk)->delete($this->import->path);
            }
        }
    }

    /** @return array{format: string, content: string}|null */
    private function resolveImport(DiagramSqlService $service): ?array
    {
        if ($this->import === null) {
            $currentScript = $this->diagram->script;
            if ($this->expectedScriptHash === null
                || ! is_string($currentScript)
                || ! hash_equals($this->expectedScriptHash, hash('sha256', $currentScript))) {
                return null;
            }

            return $service->decodeQueuedImport($currentScript);
        }

        $this->import->refresh();
        if ($this->import->diagram_id !== $this->diagram->id
            || ! in_array($this->import->status, [DiagramImport::STATUS_UPLOADED, DiagramImport::STATUS_PROCESSING], true)) {
            return null;
        }

        $this->import->status = DiagramImport::STATUS_PROCESSING;
        $this->import->save();

        $path = is_string($this->import->path)
            ? $this->import->path
            : $service->assembleChunkedImport($this->import);

        $this->import->refresh();

        return [
            'format' => $this->import->format,
            'content' => Storage::disk($this->import->disk)->get($path),
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->diagram->import_status = ImportStatus::FAILED;
        $this->diagram->import_error = $exception->getMessage();
        $this->diagram->save();

        if ($this->import !== null) {
            $this->import->status = DiagramImport::STATUS_FAILED;
            $this->import->error = $exception->getMessage();
            $this->import->save();
        }

        Log::error('ImportDiagramSchemaJob failed', [
            'diagram_id' => $this->diagram->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
