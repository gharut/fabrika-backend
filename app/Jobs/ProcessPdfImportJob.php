<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Models\FileOperation;
use App\Services\ChestnyZnakLabelService;
use Exception;
use Throwable;

class ProcessPdfImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    public function __construct(
        public int $operationId,
        public string $storagePath,
        public int $sizeId = 0,
        public int $clientId = 0,
        public int $userId = 0
    ) {}

    public function handle(ChestnyZnakLabelService $importService)
    {   
        if ($this->clientId) {
            app(\App\Support\ClientContext::class)->set($this->clientId);
            \Log::channel('jobs')->debug('ClientContext установлен', ['client_id' => $this->clientId]);
        }

        $op = FileOperation::find($this->operationId);
        if (!$op) {
            Log::channel('jobs')->warning('ProcessPdfImportJob: операция не найдена', [
                'operation_id' => $this->operationId,
                'path' => $this->storagePath,
            ]);
            return;
        }

        Log::channel('jobs')->info('ProcessPdfImportJob запущена', [
            'operation_id' => $this->operationId,
            'path' => $this->storagePath,
            'size_id' => $this->sizeId,
        ]);

        try {
            $localPath = storage_path('app/public/' . $this->storagePath);

            if (!file_exists($localPath)) {
                throw new Exception("Файл не найден: {$localPath}");
            }

            $client = new Client([
                'base_uri' => 'http://127.0.0.1:8001',
                'timeout' => 300,
            ]);

            Log::channel('jobs')->info('Отправка запроса на decode-pdf', [
                'operation_id' => $this->operationId,
                'file' => basename($localPath),
            ]);

            $response = $client->request('POST', '/decode-pdf', [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => fopen($localPath, 'r'),
                        'filename' => basename($localPath),
                    ],
                ],
            ]);

            $body = (string)$response->getBody();
            Log::channel('jobs')->debug('Ответ от /decode-pdf', [
                'operation_id' => $this->operationId,
                'response_snippet' => mb_substr($body, 0, 500) . '...',
            ]);

            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $pages = $data['pages'] ?? [];

            $totalPages = count($pages);
            $processedPages = 0;
            $createdCount = 0;
            $errors = [];

            if ($totalPages === 0) {
                Log::channel('jobs')->warning('PDF не содержит страниц', [
                    'operation_id' => $this->operationId,
                    'response' => $data,
                ]);
            }

            Log::channel('jobs')->info("Начало обработки PDF ({$this->operationId})", [
                'total_pages' => $totalPages,
                'size_id' => $this->sizeId,
            ]);

            foreach ($pages as $pageIndex => $page) {
                $codes = array_column($page['codes'] ?? [], 'raw_text_utf8');

                $result = $importService->processCodes(
                    $this->operationId,
                    $this->sizeId,
                    $codes,
                    $this->userId,
                );

                $createdCount += $result['created_count'] ?? 0;
                $errors = array_merge($errors, $result['errors'] ?? []);
                $processedPages++;
            }

            $totalCodes = array_sum(array_map(fn($p) => count($p['codes'] ?? []), $pages));
            $totalErrors = count($errors);
            $totalSuccess = max($totalCodes - $totalErrors, 0);

            $errorGroups = [];
            foreach ($errors as $err) {
                $msg = $err['message'] ?? 'Неизвестная ошибка';
                $errorGroups[$msg] = ($errorGroups[$msg] ?? 0) + 1;
            }

            $errorSummary = [
                'summary' => [
                    'total' => $totalCodes,
                    'success' => $totalSuccess,
                    'failed' => $totalErrors,
                ],
                'details' => $errorGroups,
            ];

            $op->update([
                'status' => $totalErrors > 0 ? FileOperation::STATUS_PARTIAL_SUCCESS : FileOperation::STATUS_SUCCESS,
                'error_message' => json_encode($errorSummary, JSON_UNESCAPED_UNICODE),
                'finished_at' => now(),
            ]);

            Log::channel('jobs')->info('ProcessPdfImportJob завершена', [
                'operation_id' => $this->operationId,
                'created_count' => $createdCount,
                'errors_count' => $totalErrors,
            ]);

            if (!empty($errors)) {
                Log::channel('jobs')->warning("Ошибки при обработке PDF ({$this->operationId})", [
                    'errors' => $errors,
                ]);
            }
        } catch (Throwable $e) {
            Log::channel('jobs')->error('ProcessPdfImportJob FAILED', [
                'operation_id' => $this->operationId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $op->update([
                'status' => FileOperation::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            if (Storage::exists($this->storagePath)) {
                Storage::delete($this->storagePath);
                Log::channel('jobs')->debug('Удалён временный файл', [
                    'path' => $this->storagePath,
                ]);
            }
        }
    }
}
