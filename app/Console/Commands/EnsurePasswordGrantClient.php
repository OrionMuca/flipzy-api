<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EnsurePasswordGrantClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:ensure-password-grant-client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure Passport password grant client exists';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $client = Client::where('grant_types', 'like', '%password%')
                ->where('revoked', false)
                ->first();

            if ($client) {
                // Check if plain secret is in cache
                $cachedSecret = Cache::get("passport_client_secret_{$client->id}");
                
                if (!$cachedSecret) {
                    // Secret not in cache - we need to get it from DB
                    // If it was stored plain, use it; if hashed, we can't recover it
                    $dbClient = DB::table('oauth_clients')
                        ->where('id', $client->id)
                        ->first();
                    
                    // If secret looks like it might be hashed (starts with $2y$), we can't use it
                    // Otherwise, assume it's plain and cache it
                    if ($dbClient && !str_starts_with($dbClient->secret ?? '', '$2y$')) {
                        Cache::forever("passport_client_secret_{$client->id}", $dbClient->secret);
                        $this->info("Password grant client already exists (secret cached):");
                        $this->printEnvHint($client->id, $dbClient->secret);
                        return 0;
                    } else {
                        $this->warn("Password grant client exists but secret is hashed. Recreating...");
                        // Revoke old client
                        DB::table('oauth_clients')
                            ->where('id', $client->id)
                            ->update(['revoked' => true]);
                        // Continue to create new one
                        $client = null;
                    }
                } else {
                    $this->info("Password grant client already exists:");
                }
                
                if ($client && $cachedSecret) {
                    $this->line("  ID: {$client->id}");
                    $this->line("  Name: {$client->name}");
                    $this->printEnvHint($client->id, $cachedSecret);
                    return 0;
                }
            }

            $this->warn("Password grant client not found. Creating one...");
            
            $clientId = Str::uuid();
            $plainSecret = Str::random(40);
            
            // Store secret hashed in DB (for security), but keep plain in cache
            DB::table('oauth_clients')->insert([
                'id' => $clientId,
                'name' => 'Flipzy Password Grant Client',
                'secret' => bcrypt($plainSecret), // Hash for storage
                'provider' => 'users',
                'redirect_uris' => json_encode([config('app.url') . '/oauth/callback']),
                'grant_types' => json_encode(['password']),
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Cache::forever("passport_client_secret_{$clientId}", $plainSecret);
            
            $this->info("Password grant client created successfully:");
            $this->line("  ID: {$clientId}");
            $this->line("  Name: Flipzy Password Grant Client");
            $this->line("  Secret: {$plainSecret}");
            $this->printEnvHint($clientId, $plainSecret);
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create password grant client: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Print .env lines so auth keeps working when cache is lost (e.g. container restart).
     */
    protected function printEnvHint(string $clientId, string $secret): void
    {
        $this->newLine();
        $this->comment('Add to .env to avoid "Password grant client secret not available" when cache is lost:');
        $this->line("PASSPORT_PASSWORD_GRANT_CLIENT_ID={$clientId}");
        $this->line("PASSPORT_PASSWORD_GRANT_CLIENT_SECRET={$secret}");
    }
}

