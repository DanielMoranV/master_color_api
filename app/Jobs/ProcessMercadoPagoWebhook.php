<?php

namespace App\Jobs;

use App\Facades\MercadoPago;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMercadoPagoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The full webhook payload.
     */
    private array $payload;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        // Guardar tamaño para debug
        Log::debug('📥 Job ProcessMercadoPagoWebhook dispatched', [
            'payload_keys' => array_keys($payload),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('🏗 Procesando webhook en background', [
                'action' => $this->payload['action'] ?? null,
                'type' => $this->payload['type'] ?? null,
            ]);

            $processed = MercadoPago::processWebhookNotification($this->payload);

            Log::info('✅ Resultado del procesamiento de webhook', [
                'success' => $processed,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Error en job ProcessMercadoPagoWebhook', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
