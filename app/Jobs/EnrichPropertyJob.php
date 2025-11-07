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
            $enrichmentService->performEnrichment($this->property, $this->forceFresh);
        } catch (\Exception $e) {
            Log::error('Property enrichment job failed', [
                'property_id' => $this->property->id,
                'error' => $e->getMessage(),
            ]);

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
