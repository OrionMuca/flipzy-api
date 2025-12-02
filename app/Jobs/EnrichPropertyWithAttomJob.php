<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\PropertySeedingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichPropertyWithAttomJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Property $property,
        public array $endpoints = ['detail', 'sale_history', 'comparable_sales', 'events'],
        public bool $forceFresh = false
    ) {
        // Set queue name for rate limiting
        $this->onQueue('attom-enrichment');
    }

    /**
     * Execute the job.
     */
    public function handle(PropertySeedingService $seedingService): void
    {
        try {
            Log::info('Starting ATTOM enrichment job', [
                'property_id' => $this->property->id,
                'endpoints' => $this->endpoints,
            ]);

            $seedingService->enrichProperty(
                $this->property,
                $this->endpoints,
                $this->forceFresh
            );

            Log::info('ATTOM enrichment job completed', [
                'property_id' => $this->property->id,
            ]);

        } catch (\Exception $e) {
            Log::error('ATTOM enrichment job failed', [
                'property_id' => $this->property->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ATTOM enrichment job permanently failed', [
            'property_id' => $this->property->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
