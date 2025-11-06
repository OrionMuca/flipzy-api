<?php

namespace App\Console\Commands;

use App\Jobs\EnrichPropertyJob;
use App\Models\Property;
use Illuminate\Console\Command;

class EnrichAllPropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'properties:enrich-all 
                            {--fresh : Force fresh data (bypass cache)}
                            {--days=30 : Only enrich properties enriched more than X days ago (default: 30)}
                            {--un-enriched : Only enrich properties that have never been enriched}
                            {--sync : Run synchronously instead of queuing jobs}
                            {--limit= : Limit the number of properties to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich all properties with data from external APIs (ATTOM)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting property enrichment process...');
        $this->newLine();

        // Build query based on options
        $query = Property::query();

        // Filter based on options
        if ($this->option('un-enriched')) {
            // Only properties that have never been enriched
            $query->whereNull('enriched_at');
            $this->info('Mode: Enriching only un-enriched properties');
        } elseif ($this->option('fresh')) {
            // All properties (will force fresh data)
            $this->info('Mode: Enriching all properties with fresh data (cache bypassed)');
        } else {
            // Properties enriched more than X days ago (default: 30)
            $days = (int) $this->option('days');
            $query->where(function ($q) use ($days) {
                $q->whereNull('enriched_at')
                  ->orWhere('enriched_at', '<', now()->subDays($days));
            });
            $this->info("Mode: Enriching properties enriched more than {$days} days ago or never enriched");
        }

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $properties = $query->get();
        $total = $properties->count();

        if ($total === 0) {
            $this->warn('No properties found to enrich.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} property/properties to enrich.");
        $this->newLine();

        // Show summary
        $enrichedCount = Property::whereNotNull('enriched_at')->count();
        $unEnrichedCount = Property::whereNull('enriched_at')->count();
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Properties', Property::count()],
                ['Already Enriched', $enrichedCount],
                ['Never Enriched', $unEnrichedCount],
                ['To Process', $total],
            ]
        );
        $this->newLine();

        // Confirm if not running in quiet mode
        if (!$this->option('sync') && !$this->confirm('Do you want to proceed? (Properties will be queued for async processing)')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Process properties
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $queued = 0;
        $errors = 0;

        foreach ($properties as $property) {
            try {
                // Skip properties without address (required for ATTOM)
                if (empty($property->address)) {
                    $this->newLine();
                    $this->warn("Skipping property {$property->id}: Missing address");
                    $bar->advance();
                    continue;
                }
                
                $forceFresh = $this->option('fresh');
                
                if ($this->option('sync')) {
                    // Synchronous enrichment
                    app(\App\Services\PropertyEnrichmentService::class)
                        ->performEnrichment($property, $forceFresh);
                    $synced++;
                } else {
                    // Queue for async processing
                    EnrichPropertyJob::dispatch($property, $forceFresh);
                    $queued++;
                }

                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Failed to enrich property {$property->id}: {$e->getMessage()}");
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Enrichment process completed!');
        $this->newLine();

        if ($this->option('sync')) {
            $this->table(
                ['Status', 'Count'],
                [
                    ['Successfully Enriched', $synced],
                    ['Errors', $errors],
                ]
            );
        } else {
            $this->table(
                ['Status', 'Count'],
                [
                    ['Queued for Processing', $queued],
                    ['Errors', $errors],
                ]
            );
            $this->info('Note: Properties are queued. Run "php artisan queue:work" to process them.');
        }

        return Command::SUCCESS;
    }
}
