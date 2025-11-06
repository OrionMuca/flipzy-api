<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\PropertyEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichPropertyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Property $property,
        public bool $forceFresh = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PropertyEnrichmentService $enrichmentService): void
    {
        try {
            Log::info('Starting property enrichment', [
                'property_id' => $this->property->id,
                'address' => $this->property->address,
            ]);

            $enrichmentService->performEnrichment($this->property, $this->forceFresh);

            Log::info('Property enrichment completed', [
                'property_id' => $this->property->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Property enrichment job failed', [
                'property_id' => $this->property->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Property enrichment job permanently failed', [
            'property_id' => $this->property->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
