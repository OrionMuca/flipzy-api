<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PropertySeedingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverPropertiesFromAttomJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 120; // seconds

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $criteria,
        public ?string $wholesalerId = null,
        public int $limit = 10
    ) {
        $this->onQueue('attom-discovery');
    }

    /**
     * Execute the job.
     */
    public function handle(PropertySeedingService $seedingService): void
    {
        try {
            $wholesaler = $this->wholesalerId ? User::find($this->wholesalerId) : null;

            Log::info('Starting property discovery from ATTOM', [
                'criteria' => $this->criteria,
                'limit' => $this->limit,
            ]);

            $results = $seedingService->discoverProperties(
                $this->criteria,
                $wholesaler,
                $this->limit
            );

            Log::info('Property discovery completed', [
                'found' => $results['found'],
                'created' => $results['created'],
                'skipped' => $results['skipped'],
            ]);

        } catch (\Exception $e) {
            Log::error('Property discovery job failed', [
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
        Log::error('Property discovery job permanently failed', [
            'criteria' => $this->criteria,
            'error' => $exception->getMessage(),
        ]);
    }
}
