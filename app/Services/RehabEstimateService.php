<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyRehabEstimate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class RehabEstimateService
{
    protected string $defaultModel = 'gpt-3.5-turbo';
    protected int $cacheDuration = 7; // days

    /**
     * Generate rehab cost estimate for a property
     */
    public function generateEstimate(
        Property $property,
        ?User $user = null,
        array $options = []
    ): PropertyRehabEstimate {
        // Check if API key is configured
        if (!$this->isApiKeyConfigured()) {
            throw new \RuntimeException(
                'OpenAI API key is not configured. Please set OPENAI_API_KEY in your .env file.'
            );
        }

        $model = $options['model'] ?? config('services.openai.model', $this->defaultModel);
        $forceRefresh = $options['force_refresh'] ?? false;

        // Check cache first (unless force refresh)
        if (!$forceRefresh) {
            $cachedEstimate = $this->getCachedEstimate($property);
            if ($cachedEstimate) {
                return $cachedEstimate;
            }
        }

        // Prepare property data
        $propertyData = $this->preparePropertyData($property);

        // Build prompt
        $prompt = $this->buildPrompt($propertyData);

        try {
            // Call OpenAI API (pass propertyData for vision models)
            $response = $this->callOpenAI($prompt, $model, $propertyData);

            // Parse response
            $parsedResponse = $this->parseAIResponse($response);

            // Extract cost breakdown
            $costBreakdown = $this->extractCostBreakdown($parsedResponse);

            // Create estimate record
            $estimate = PropertyRehabEstimate::create([
                'property_id' => $property->id,
                'requested_by' => $user?->id,
                'ai_response' => $response['content'] ?? json_encode($parsedResponse),
                'property_data' => $propertyData,
                'model_used' => $model,
                'estimated_cost' => $costBreakdown['total_cost'] ?? null,
                'tokens_used' => $response['tokens_used'] ?? null,
            ]);

            // Cache the estimate
            $this->cacheEstimate($property, $estimate);

            return $estimate;
        } catch (\Exception $e) {
            Log::error('OpenAI API error', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Failed to generate rehab estimate: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if OpenAI API key is configured
     */
    public function isApiKeyConfigured(): bool
    {
        $apiKey = config('services.openai.api_key');
        return !empty($apiKey);
    }

    /**
     * Prepare property data for AI prompt
     */
    protected function preparePropertyData(Property $property): array
    {
        // Load images relationship
        $property->load('images');

        $data = [
            'location' => [
                'city' => $property->city,
                'state' => $property->state,
                'zip' => $property->zip_code,
                'address' => $property->address,
            ],
            'property' => [
                'type' => $property->property_type,
                'square_feet' => $property->square_feet,
                'bedrooms' => $property->bedrooms,
                'bathrooms' => $property->bathrooms,
                'year_built' => $property->year_built,
                'condition' => $property->condition,
                'lot_size' => $property->lot_size,
            ],
            'financial' => [
                'asking_price' => $property->asking_price,
                'arv' => $property->arv,
            ],
        ];

        // Add description if available
        if ($property->description) {
            $data['description'] = $property->description;
        }

        // Add images information
        if ($property->images->isNotEmpty()) {
            $data['images'] = $property->images->map(function ($image) {
                return [
                    'url' => $image->url ?? asset('storage/' . $image->path),
                    'alt_text' => $image->alt_text,
                    'is_primary' => $image->is_primary,
                    'order' => $image->order,
                ];
            })->toArray();
            
            $data['image_count'] = $property->images->count();
            $data['has_images'] = true;
        } else {
            $data['has_images'] = false;
            $data['image_count'] = 0;
        }

        // Extract issues from ATTOM data if available
        if ($property->attom_data) {
            $attomData = $property->attom_data;
            
            // Extract building condition or issues from ATTOM
            if (isset($attomData['building']['size']['bldgsize'])) {
                $data['property']['building_size'] = $attomData['building']['size']['bldgsize'];
            }
            
            // Add any other relevant ATTOM data
            if (isset($attomData['building']['rooms'])) {
                $data['property']['rooms'] = $attomData['building']['rooms'];
            }
        }

        return $data;
    }

    /**
     * Build the prompt for OpenAI
     */
    protected function buildPrompt(array $propertyData): string
    {
        $location = "{$propertyData['location']['city']}, {$propertyData['location']['state']}";
        $property = $propertyData['property'];
        $financial = $propertyData['financial'];

        $prompt = "You are an expert real estate rehabilitation cost estimator with 20+ years of experience in property flipping and renovation. You specialize in providing accurate, detailed cost estimates for residential properties in the United States.

Your estimates should:
- Be based on current market rates (2024-2025)
- Account for regional cost variations
- Include both labor and materials
- Provide detailed breakdowns by room/category
- Identify potential risk factors
- Consider property age and condition
- Account for local building codes and permits
- Analyze visual condition from property images when available

Property Details:
- Location: {$location}
- Property Type: {$property['type']}
- Square Feet: {$property['square_feet']}
- Bedrooms: {$property['bedrooms']}
- Bathrooms: {$property['bathrooms']}
- Year Built: {$property['year_built']}
- Condition: {$property['condition']}
- Lot Size: {$property['lot_size']} sq ft
- Asking Price: $" . number_format($financial['asking_price'], 2) . "
- ARV (After Repair Value): $" . number_format($financial['arv'] ?? 0, 2) . "
";

        if (isset($propertyData['description'])) {
            $prompt .= "- Description/Notes: {$propertyData['description']}\n";
        }

        // Add image information
        if (isset($propertyData['has_images']) && $propertyData['has_images']) {
            $imageCount = $propertyData['image_count'] ?? 0;
            $prompt .= "\n- Property Images: {$imageCount} image(s) available\n";
            
            if (isset($propertyData['images']) && is_array($propertyData['images'])) {
                $primaryImage = collect($propertyData['images'])->firstWhere('is_primary', true);
                if ($primaryImage) {
                    $prompt .= "- Primary Image URL: {$primaryImage['url']}\n";
                    if (!empty($primaryImage['alt_text'])) {
                        $prompt .= "- Primary Image Description: {$primaryImage['alt_text']}\n";
                    }
                }
                
                // List all image URLs for vision models
                $imageUrls = collect($propertyData['images'])->pluck('url')->toArray();
                if (count($imageUrls) > 0) {
                    $prompt .= "- All Image URLs: " . implode(', ', $imageUrls) . "\n";
                }
            }
            
            $prompt .= "\nIMPORTANT: When analyzing images, consider:\n";
            $prompt .= "- Visible damage, wear, or deterioration\n";
            $prompt .= "- Quality of finishes and materials\n";
            $prompt .= "- Need for cosmetic vs. structural repairs\n";
            $prompt .= "- Kitchen and bathroom condition\n";
            $prompt .= "- Flooring condition\n";
            $prompt .= "- Exterior condition (if exterior images provided)\n";
        } else {
            $prompt .= "\n- Property Images: No images available (estimate based on property parameters only)\n";
        }

        $prompt .= "\nPlease provide a detailed cost estimate in the following JSON format:
{
  \"total_cost\": 50000,
  \"breakdown\": {
    \"kitchen\": 15000,
    \"bathrooms\": 12000,
    \"flooring\": 8000,
    \"paint\": 3000,
    \"electrical\": 4000,
    \"plumbing\": 5000,
    \"hvac\": 3000,
    \"roof\": 0,
    \"windows\": 0,
    \"other\": 0
  },
  \"labor_percentage\": 40,
  \"materials_percentage\": 60,
  \"timeline_weeks\": 8,
  \"risk_factors\": [\"Older electrical system may need full replacement\"],
  \"notes\": \"Detailed explanation of estimate\",
  \"confidence\": \"high\"
}

IMPORTANT: Return ONLY valid JSON, no additional text or markdown formatting.";

        return $prompt;
    }

    /**
     * Check if model supports vision (image analysis)
     */
    protected function isVisionModel(string $model): bool
    {
        return str_contains(strtolower($model), 'vision') || 
               str_contains(strtolower($model), 'gpt-4o') ||
               str_contains(strtolower($model), 'gpt-4-turbo');
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(string $prompt, string $model, array $propertyData = []): array
    {
        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are an expert real estate rehabilitation cost estimator. Always respond with valid JSON only, no markdown formatting. When analyzing property images, carefully assess visible condition, damage, and renovation needs.',
                ],
            ];

            // If vision model and images available, include images in the message
            if ($this->isVisionModel($model) && isset($propertyData['has_images']) && $propertyData['has_images'] && isset($propertyData['images'])) {
                $content = [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                ];

                // Add images to content (vision models can analyze them)
                foreach ($propertyData['images'] as $image) {
                    if (isset($image['url'])) {
                        $content[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $image['url'],
                            ],
                        ];
                    }
                }

                $messages[] = [
                    'role' => 'user',
                    'content' => $content,
                ];
            } else {
                // Standard text-only prompt
                $messages[] = [
                    'role' => 'user',
                    'content' => $prompt,
                ];
            }

            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3, // Lower temperature for more consistent, factual responses
                'response_format' => ['type' => 'json_object'], // Request JSON response
            ]);

            $content = $response->choices[0]->message->content;
            $tokensUsed = $response->usage->totalTokens ?? null;

            return [
                'content' => $content,
                'tokens_used' => $tokensUsed,
            ];
        } catch (\Exception $e) {
            // Catch any exception (ErrorException, TransportException, etc.)
            Log::error('OpenAI API error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);

            throw new \RuntimeException(
                'OpenAI API error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Parse AI response
     */
    protected function parseAIResponse(array $response): array
    {
        $content = $response['content'] ?? '';

        // Try to extract JSON from response (in case of markdown formatting)
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse OpenAI response as JSON', [
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);

            // Return a default structure if parsing fails
            return [
                'total_cost' => null,
                'breakdown' => [],
                'notes' => $content, // Store raw content as notes
            ];
        }

        return $parsed;
    }

    /**
     * Extract cost breakdown from parsed response
     */
    protected function extractCostBreakdown(array $parsed): array
    {
        return [
            'total_cost' => $parsed['total_cost'] ?? null,
            'breakdown' => $parsed['breakdown'] ?? [],
            'labor_percentage' => $parsed['labor_percentage'] ?? null,
            'materials_percentage' => $parsed['materials_percentage'] ?? null,
            'timeline_weeks' => $parsed['timeline_weeks'] ?? null,
            'risk_factors' => $parsed['risk_factors'] ?? [],
            'notes' => $parsed['notes'] ?? '',
            'confidence' => $parsed['confidence'] ?? 'medium',
        ];
    }

    /**
     * Get cached estimate for property
     */
    protected function getCachedEstimate(Property $property): ?PropertyRehabEstimate
    {
        $cacheKey = $this->getCacheKey($property);
        $estimateId = Cache::get($cacheKey);

        if ($estimateId) {
            return PropertyRehabEstimate::find($estimateId);
        }

        return null;
    }

    /**
     * Cache estimate
     */
    protected function cacheEstimate(Property $property, PropertyRehabEstimate $estimate): void
    {
        $cacheKey = $this->getCacheKey($property);
        Cache::put($cacheKey, $estimate->id, now()->addDays($this->cacheDuration));
    }

    /**
     * Get cache key for property
     */
    protected function getCacheKey(Property $property): string
    {
        // Create hash based on property data that affects estimate
        $dataHash = md5(json_encode([
            $property->id,
            $property->square_feet,
            $property->bedrooms,
            $property->bathrooms,
            $property->year_built,
            $property->condition,
            $property->property_type,
            $property->updated_at->timestamp,
        ]));

        return "rehab_estimate_{$property->id}_{$dataHash}";
    }

    /**
     * Clear cache for property
     */
    public function clearCache(Property $property): void
    {
        // Clear all possible cache keys for this property
        Cache::forget("rehab_estimate_{$property->id}_*");
    }

    /**
     * Get estimate history for a property
     */
    public function getEstimateHistory(Property $property, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return PropertyRehabEstimate::where('property_id', $property->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}

