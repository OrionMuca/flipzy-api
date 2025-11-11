<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;

class EnsurePassportClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:ensure-client {--provider=users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure Passport personal access client exists for the specified provider';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provider = $this->option('provider');
        $repository = new ClientRepository();

        try {
            $client = $repository->personalAccessClient($provider);
            $this->info("Personal access client already exists for provider '{$provider}':");
            $this->line("  ID: {$client->id}");
            $this->line("  Name: {$client->name}");
            return 0;
        } catch (\RuntimeException $e) {
            $this->warn("Personal access client not found for provider '{$provider}'. Creating one...");
            
            try {
                $client = $repository->createPersonalAccessGrantClient(
                    "Flipzy Personal Access Client ({$provider})",
                    $provider
                );
                
                $this->info("Personal access client created successfully:");
                $this->line("  ID: {$client->id}");
                $this->line("  Name: {$client->name}");
                $this->line("  Provider: {$provider}");
                return 0;
            } catch (\Exception $e) {
                $this->error("Failed to create personal access client: " . $e->getMessage());
                return 1;
            }
        }
    }
}
