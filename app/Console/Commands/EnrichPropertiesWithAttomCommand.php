<?php

namespace App\Console\Commands;

use App\Jobs\EnrichPropertyWithAttomJob;
use App\Models\Property;
use App\Services\PropertySeedingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class EnrichPropertiesWithAttomCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'properties:enrich-attom
                            {--property-id= : Enrich a specific property by ID}
                            {--batch : Process in batch mode (queue jobs)}
                            {--force : Force fresh data (bypass cache)}
                            {--endpoints= : Comma-separated list of endpoints (detail,sale_history,comparable_sales,events)}
                            {--limit= : Limit number of properties to process}
                            {--days-old= : Only enrich properties enriched more than X days ago}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich properties with comprehensive ATTOM data';

    /**
     * Execute the console command.
     */
    public function handle(PropertySeedingService $seedingService): int
    {
        $endpoints = $this->parseEndpoints();
        $forceFresh = $this->option('force');
        $batchMode = $this->option('batch');

        // Single property enrichment
        if ($propertyId = $this->option('property-id')) {
            return $this->enrichSingleProperty($propertyId, $endpoints, $forceFresh, $batchMode, $seedingService);
        }

        // Batch enrichment
        return $this->enrichMultipleProperties($endpoints, $forceFresh, $batchMode, $seedingService);
    }

    /**
     * Enrich a single property
     */
    protected function enrichSingleProperty(string $propertyId, array $endpoints, bool $forceFresh, bool $batchMode, PropertySeedingService $seedingService): int
    {
        $property = Property::find($propertyId);

        if (!$property) {
            $this->error("Property with ID {$propertyId} not found.");
            return Command::FAILURE;
        }

        $this->info("Enriching property: {$property->title} (ID: {$property->id})");

        if ($batchMode) {
            EnrichPropertyWithAttomJob::dispatch($property, $endpoints, $forceFresh);
            $this->info('Enrichment job queued.');
            return Command::SUCCESS;
        }

        try {
            $seedingService->enrichProperty($property, $endpoints, $forceFresh);
            $this->info('Property enriched successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Enrichment failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Enrich multiple properties
     */
    protected function enrichMultipleProperties(array $endpoints, bool $forceFresh, bool $batchMode, PropertySeedingService $seedingService): int
    {
        $query = Property::query();

        // Filter by days old
        if ($daysOld = $this->option('days-old')) {
            $query->where(function ($q) use ($daysOld) {
                $q->whereNull('attom_enriched_at')
                  ->orWhere('attom_enriched_at', '<', now()->subDays((int) $daysOld));
            });
            $this->info("Filtering properties enriched more than {$daysOld} days ago or never enriched");
        }

        // Apply limit
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $properties = $query->get();
        $count = $properties->count();

        if ($count === 0) {
            $this->warn('No properties found to enrich.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} property/properties to enrich.");

        if (!$this->confirm('Do you want to proceed?', true)) {
            return Command::SUCCESS;
        }

        if ($batchMode) {
            // Queue jobs in batches
            $jobs = $properties->map(function ($property) use ($endpoints, $forceFresh) {
                return new EnrichPropertyWithAttomJob($property, $endpoints, $forceFresh);
            });

            Bus::batch($jobs)
                ->name('ATTOM Property Enrichment')
                ->dispatch();

            $this->info("Queued {$count} enrichment jobs.");
            return Command::SUCCESS;
        }

        // Process synchronously with progress bar
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($properties as $property) {
            try {
                $seedingService->enrichProperty($property, $endpoints, $forceFresh);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->warn("Failed to enrich property {$property->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Enrichment completed: {$success} succeeded, {$failed} failed.");

        return Command::SUCCESS;
    }

    /**
     * Parse endpoints option
     */
    protected function parseEndpoints(): array
    {
        $endpointsOption = $this->option('endpoints');
        
        if (!$endpointsOption) {
            return ['detail', 'sale_history', 'comparable_sales', 'events'];
        }

        $endpoints = array_map('trim', explode(',', $endpointsOption));
        $validEndpoints = ['detail', 'sale_history', 'comparable_sales', 'events'];
        
        return array_intersect($endpoints, $validEndpoints);
    }
}
