<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
class PasswordGrantService
{
    protected ClientRepository $clientRepository;

    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    /**
     * Get or create password grant client
     * Returns array with id and plain secret (since Passport hashes secrets).
     * Prefers .env (PASSPORT_PASSWORD_GRANT_CLIENT_ID / PASSPORT_PASSWORD_GRANT_CLIENT_SECRET) so auth keeps working when cache is lost.
     */
    public function getPasswordGrantClient(): array
    {
        // Prefer .env so login works even after cache clear / container restart
        $envId = config('services.passport.password_grant_client_id');
        $envSecret = config('services.passport.password_grant_client_secret');
        if ($envId && $envSecret) {
            $exists = DB::table('oauth_clients')
                ->where('id', $envId)
                ->where('grant_types', 'like', '%password%')
                ->where('revoked', false)
                ->exists();
            if ($exists) {
                return ['id' => $envId, 'secret' => $envSecret];
            }
        }

        $client = DB::table('oauth_clients')
            ->where('grant_types', 'like', '%password%')
            ->where('revoked', false)
            ->first();

        if ($client) {
            $plainSecret = Cache::get("passport_client_secret_{$client->id}");
            
            // If not in cache, check if DB has plain secret (for backwards compatibility)
            if (!$plainSecret) {
                $dbClient = DB::table('oauth_clients')->where('id', $client->id)->first();
                
                // If secret doesn't look hashed (doesn't start with $2y$), it might be plain
                if ($dbClient && !str_starts_with($dbClient->secret ?? '', '$2y$')) {
                    $plainSecret = $dbClient->secret;
                    // Cache it for future use
                    Cache::forever("passport_client_secret_{$client->id}", $plainSecret);
                } else {
                    // Secret is hashed and not in cache - can't recover
                    Log::error('Password grant client secret not available in cache and DB secret is hashed.', [
                        'client_id' => $client->id,
                    ]);
                    
                    throw new \Exception(
                        'Password grant client secret not available. Please run: php artisan passport:ensure-password-grant-client ' .
                        'to recreate the client with a new secret.'
                    );
                }
            }
            
            return [
                'id' => $client->id,
                'secret' => $plainSecret,
            ];
        }

        // Create new password grant client
        try {
            // Generate plain secret first (we need this for token requests)
            $plainSecret = Str::random(40);
            
            // Create client using DB directly to avoid Passport's secret hashing
            // Passport's password grant validates by comparing hashed input with stored hash
            // But we need the plain secret for requests, so we'll store it plain
            // and Passport will hash it during validation
            $clientId = Str::uuid();
            DB::table('oauth_clients')->insert([
                'id' => $clientId,
                'name' => 'Flipzy Password Grant Client',
                'secret' => bcrypt($plainSecret),
                'provider' => 'users',
                'redirect_uris' => json_encode([config('app.url') . '/oauth/callback']),
                'grant_types' => json_encode(['password']),
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forever("passport_client_secret_{$clientId}", $plainSecret);

            return [
                'id' => $clientId,
                'secret' => $plainSecret,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create password grant client', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate access token using password grant
     */
    public function generateToken(string $email, string $password): array
    {
        $client = $this->getPasswordGrantClient();
        $plainSecret = Cache::get("passport_client_secret_{$client['id']}", $client['secret']);

        $request = Request::create('/oauth/token', 'POST', [
            'grant_type' => 'password',
            'client_id' => $client['id'],
            'client_secret' => $plainSecret,
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ]);

        $request->headers->set('Content-Type', 'application/x-www-form-urlencoded');
        $request->headers->set('Accept', 'application/json');

        $response = app()->handle($request);
        $responseData = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === 200 && isset($responseData['access_token'])) {
            return $responseData;
        }

        Log::error('Password grant token generation failed', [
            'status' => $response->getStatusCode(),
            'body' => $response->getContent(),
            'email' => $email,
            'client_id' => $client['id'],
        ]);

        throw new \Exception('Failed to generate access token: ' . ($responseData['message'] ?? $response->getContent()));
    }
}

