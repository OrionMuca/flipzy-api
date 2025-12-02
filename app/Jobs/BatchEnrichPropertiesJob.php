<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\PropertySeedingService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BatchEnrichPropertiesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Batch jobs shouldn't retry, individual jobs handle retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Collection $properties,
        public array $endpoints = ['detail', 'sale_history', 'comparable_sales', 'events'],
        public bool $forceFresh = false
    ) {
        $this->onQueue('attom-enrichment');
    }

    /**
     * Execute the job.
     */
    public function handle(PropertySeedingService $seedingService): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        try {
            Log::info('Starting batch ATTOM enrichment', [
                'property_count' => $this->properties->count(),
                'endpoints' => $this->endpoints,
            ]);

            $results = $seedingService->batchEnrichProperties(
                $this->properties,
                $this->endpoints,
                $this->forceFresh
            );

            Log::info('Batch ATTOM enrichment completed', [
                'success' => $results['success'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped'],
            ]);

        } catch (\Exception $e) {
            Log::error('Batch ATTOM enrichment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
