<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverPropertiesFromAttomJob;
use App\Models\User;
use App\Services\PropertySeedingService;
use Illuminate\Console\Command;

class SeedPropertiesFromAttomCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'properties:seed-from-attom
                            {--city= : City to search in}
                            {--state= : State code (2 letters)}
                            {--zip= : ZIP code}
                            {--limit=10 : Maximum number of properties to seed}
                            {--wholesaler-id= : Assign properties to a specific wholesaler}
                            {--min-beds= : Minimum bedrooms}
                            {--max-beds= : Maximum bedrooms}
                            {--min-bath= : Minimum bathrooms}
                            {--max-bath= : Maximum bathrooms}
                            {--min-sqft= : Minimum square feet}
                            {--max-sqft= : Maximum square feet}
                            {--min-price= : Minimum price}
                            {--max-price= : Maximum price}
                            {--property-type= : Property type filter}
                            {--queue : Queue the discovery job instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover and seed new properties from ATTOM API';

    /**
     * Execute the console command.
     */
    public function handle(PropertySeedingService $seedingService): int
    {
        $criteria = $this->buildCriteria();
        
        if (empty($criteria)) {
            $this->error('At least one search criterion is required (city, state, or zip).');
            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $wholesalerId = $this->option('wholesaler-id');
        $wholesaler = $wholesalerId ? User::find($wholesalerId) : null;

        if ($wholesalerId && !$wholesaler) {
            $this->error("Wholesaler with ID {$wholesalerId} not found.");
            return Command::FAILURE;
        }

        $this->info('Searching for properties with criteria:');
        $this->table(['Criterion', 'Value'], collect($criteria)->map(fn($v, $k) => [$k, $v])->toArray());
        $this->newLine();

        if ($this->option('queue')) {
            DiscoverPropertiesFromAttomJob::dispatch($criteria, $wholesalerId, $limit);
            $this->info('Property discovery job queued.');
            return Command::SUCCESS;
        }

        try {
            $this->info('Discovering properties...');
            $results = $seedingService->discoverProperties($criteria, $wholesaler, $limit);

            $this->newLine();
            $this->info('Discovery Results:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Found', $results['found']],
                    ['Created', $results['created']],
                    ['Skipped', $results['skipped']],
                ]
            );

            if (!empty($results['errors'])) {
                $this->newLine();
                $this->warn('Errors encountered:');
                foreach ($results['errors'] as $error) {
                    $this->line("  - {$error['error']}");
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Discovery failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Build search criteria from command options
     */
    protected function buildCriteria(): array
    {
        $criteria = [];

        if ($city = $this->option('city')) {
            $criteria['city'] = $city;
        }

        if ($state = $this->option('state')) {
            if (strlen($state) !== 2) {
                $this->warn('State should be a 2-letter code. Using as-is.');
            }
            $criteria['state'] = strtoupper($state);
        }

        if ($zip = $this->option('zip')) {
            $criteria['zip'] = $zip;
        }

        if ($minBeds = $this->option('min-beds')) {
            $criteria['minBeds'] = (int) $minBeds;
        }

        if ($maxBeds = $this->option('max-beds')) {
            $criteria['maxBeds'] = (int) $maxBeds;
        }

        if ($minBath = $this->option('min-bath')) {
            $criteria['minBath'] = (int) $minBath;
        }

        if ($maxBath = $this->option('max-bath')) {
            $criteria['maxBath'] = (int) $maxBath;
        }

        if ($minSqft = $this->option('min-sqft')) {
            $criteria['minSquareFeet'] = (int) $minSqft;
        }

        if ($maxSqft = $this->option('max-sqft')) {
            $criteria['maxSquareFeet'] = (int) $maxSqft;
        }

        if ($minPrice = $this->option('min-price')) {
            $criteria['minPrice'] = (float) $minPrice;
        }

        if ($maxPrice = $this->option('max-price')) {
            $criteria['maxPrice'] = (float) $maxPrice;
        }

        if ($propertyType = $this->option('property-type')) {
            $criteria['propertyType'] = $propertyType;
        }

        return $criteria;
    }
}
