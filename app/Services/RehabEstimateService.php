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
        $useCalculations = $options['use_calculations'] ?? false;
        $forceRefresh = $options['force_refresh'] ?? false;

        // If calculations are forced or API key is not configured, use calculation fallback
        if ($useCalculations || !$this->isApiKeyConfigured()) {
            Log::info('Using calculation-based rehab estimate', [
                'property_id' => $property->id,
                'reason' => $useCalculations ? 'forced' : 'openai_key_missing',
            ]);
            return $this->generateEstimateWithCalculations($property, $user, $forceRefresh);
        }

        $model = $options['model'] ?? config('services.openai.model', $this->defaultModel);

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

    /**
     * Generate rehab estimate from property data (for pre-submission/preview)
     * This doesn't require a Property model - works with array data from ATTOM lookup
     */
    public function generateEstimateFromData(
        array $propertyData,
        ?User $user = null,
        array $options = []
    ): array {
        $useCalculations = $options['use_calculations'] ?? false;
        $forceRefresh = $options['force_refresh'] ?? false;

        // Create cache key based on property characteristics
        $cacheKey = $this->getPreviewCacheKey($propertyData);

        // Check cache first (unless force refresh)
        if (!$forceRefresh) {
            $cachedEstimate = Cache::get($cacheKey);
            if ($cachedEstimate) {
                Log::info('Returning cached preview estimate', ['cache_key' => $cacheKey]);
                return $cachedEstimate;
            }
        }

        // If calculations are forced or API key is not configured, use calculation fallback
        if ($useCalculations || !$this->isApiKeyConfigured()) {
            Log::info('Using calculation-based preview estimate', [
                'reason' => $useCalculations ? 'forced' : 'openai_key_missing',
            ]);
            $estimate = $this->generateEstimateWithCalculationsFromData($propertyData, $user);
            
            // Cache the result
            Cache::put($cacheKey, $estimate, now()->addDays($this->cacheDuration));
            
            return $estimate;
        }

        $model = $options['model'] ?? config('services.openai.model', $this->defaultModel);

        // Build prompt
        $prompt = $this->buildPrompt($propertyData);

        try {
            // Call OpenAI API
            $response = $this->callOpenAI($prompt, $model, $propertyData);

            // Parse response
            $parsedResponse = $this->parseAIResponse($response);

            // Extract cost breakdown
            $costBreakdown = $this->extractCostBreakdown($parsedResponse);

            // Build estimate result (no database record for preview)
            $estimate = [
                'estimated_cost' => $costBreakdown['total_cost'] ?? null,
                'breakdown' => $costBreakdown['breakdown'] ?? [],
                'labor_percentage' => $costBreakdown['labor_percentage'] ?? null,
                'materials_percentage' => $costBreakdown['materials_percentage'] ?? null,
                'timeline_weeks' => $costBreakdown['timeline_weeks'] ?? null,
                'risk_factors' => $costBreakdown['risk_factors'] ?? [],
                'notes' => $costBreakdown['notes'] ?? '',
                'confidence' => $costBreakdown['confidence'] ?? 'medium',
                'model_used' => $model,
                'tokens_used' => $response['tokens_used'] ?? null,
                'property_data' => $propertyData,
                'is_preview' => true,
                'cached_at' => now()->toIso8601String(),
            ];

            // Cache the estimate
            Cache::put($cacheKey, $estimate, now()->addDays($this->cacheDuration));

            return $estimate;
        } catch (\Exception $e) {
            Log::error('OpenAI API error for preview estimate', [
                'error' => $e->getMessage(),
                'property_data' => $propertyData,
            ]);

            throw new \RuntimeException(
                'Failed to generate rehab estimate: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get cache key for preview estimate based on property data
     */
    protected function getPreviewCacheKey(array $propertyData): string
    {
        // Create hash based on property characteristics that affect estimate
        $location = $propertyData['location'] ?? [];
        $property = $propertyData['property'] ?? [];
        
        $dataHash = md5(json_encode([
            $location['address'] ?? '',
            $location['city'] ?? '',
            $location['state'] ?? '',
            $property['square_feet'] ?? null,
            $property['bedrooms'] ?? null,
            $property['bathrooms'] ?? null,
            $property['year_built'] ?? null,
            $property['condition'] ?? null,
            $property['type'] ?? null,
            $property['lot_size'] ?? null,
        ]));

        return "rehab_estimate_preview_{$dataHash}";
    }

    /**
     * Generate estimate using calculations from property data (no Property model)
     */
    protected function generateEstimateWithCalculationsFromData(
        array $propertyData,
        ?User $user = null
    ): array {
        $property = $propertyData['property'] ?? [];
        $location = $propertyData['location'] ?? [];

        // Calculate base cost
        $squareFeet = $property['square_feet'] ?? 0;
        $propertyType = $property['type'] ?? 'house';
        
        $costPerSqft = config("services.rehab_calculations.cost_per_sqft.{$propertyType}", 50);
        $baseCost = $squareFeet * $costPerSqft;

        // Add bedroom/bathroom costs
        $bedrooms = $property['bedrooms'] ?? 0;
        $bathrooms = $property['bathrooms'] ?? 0;
        $baseCost += ($bedrooms * config('services.rehab_calculations.bedroom_multiplier', 2000));
        $baseCost += ($bathrooms * config('services.rehab_calculations.bathroom_multiplier', 5000));

        // Apply condition multiplier
        $condition = $property['condition'] ?? 'fair';
        $conditionMultipliers = config('services.rehab_calculations.condition_multipliers', []);
        $conditionMultiplier = $conditionMultipliers[$condition] ?? 1.0;
        $baseCost *= $conditionMultiplier;

        // Apply age adjustment
        $yearBuilt = $property['year_built'] ?? null;
        if ($yearBuilt) {
            $currentYear = (int) date('Y');
            $age = $currentYear - $yearBuilt;
            
            if ($age < 10) {
                $ageMultiplier = 0.3;
            } elseif ($age < 30) {
                $ageMultiplier = 0.5;
            } elseif ($age < 50) {
                $ageMultiplier = 1.0;
            } elseif ($age < 75) {
                $ageMultiplier = 1.3;
            } else {
                $ageMultiplier = 1.6;
            }
            
            $baseCost *= $ageMultiplier;
        }

        // Ensure minimum cost
        $totalCost = max($baseCost, 5000);

        // Generate cost breakdown
        $breakdown = [
            'kitchen' => $totalCost * 0.25,
            'bathrooms' => $totalCost * 0.20,
            'flooring' => $totalCost * 0.15,
            'paint' => $totalCost * 0.10,
            'electrical' => $totalCost * 0.10,
            'plumbing' => $totalCost * 0.08,
            'hvac' => $totalCost * 0.07,
            'roofing' => $totalCost * 0.05,
        ];

        // Calculate timeline
        $timelineWeeks = max(4, (int) ($totalCost / 5000));

        // Generate risk factors
        $riskFactors = [];
        if ($condition === 'poor' || $condition === 'needs_repair') {
            $riskFactors[] = 'Property condition may require additional structural repairs';
        }
        if ($yearBuilt && (date('Y') - $yearBuilt) > 50) {
            $riskFactors[] = 'Older property may have hidden issues (plumbing, electrical, foundation)';
        }

        return [
            'estimated_cost' => $totalCost,
            'breakdown' => $breakdown,
            'labor_percentage' => 40,
            'materials_percentage' => 60,
            'timeline_weeks' => $timelineWeeks,
            'risk_factors' => $riskFactors,
            'notes' => "Calculation-based estimate for {$location['city']}, {$location['state']}. Based on property size, condition, and age.",
            'confidence' => 'medium',
            'model_used' => 'calculation-fallback',
            'tokens_used' => null,
            'property_data' => $propertyData,
            'is_preview' => true,
            'cached_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate estimate using calculation-based formulas
     */
    protected function generateEstimateWithCalculations(
        Property $property,
        ?User $user = null,
        bool $forceRefresh = false
    ): PropertyRehabEstimate {
        // Check cache first (unless force refresh)
        if (!$forceRefresh) {
            $cachedEstimate = $this->getCachedEstimate($property);
            if ($cachedEstimate && $cachedEstimate->model_used === 'calculation-fallback') {
                return $cachedEstimate;
            }
        }

        // Prepare property data
        $propertyData = $this->preparePropertyData($property);

        // Calculate base cost
        $baseCost = $this->calculateBaseCost($property, $propertyData);

        // Calculate adjustments
        $conditionAdjustment = $this->calculateConditionAdjustment($property, $baseCost);
        $ageAdjustment = $this->calculateAgeAdjustment($property, $baseCost);
        $regionalAdjustment = $this->calculateRegionalAdjustment($property, $baseCost);

        // Apply image heuristic (more images = better documented = potentially better condition)
        $imageAdjustment = $this->calculateImageAdjustment($propertyData, $baseCost);

        // Calculate total cost
        $totalCost = $baseCost 
            + $conditionAdjustment 
            + $ageAdjustment 
            + $regionalAdjustment 
            - $imageAdjustment; // Subtract because more images = lower uncertainty = potentially lower costs

        // Ensure minimum cost
        $totalCost = max($totalCost, 5000); // Minimum $5,000

        // Generate cost breakdown
        $breakdown = $this->generateCostBreakdown($totalCost, $property, $propertyData);

        // Calculate labor and materials split
        $laborPercentage = 40;
        $materialsPercentage = 60;

        // Estimate timeline based on property size and condition
        $timelineWeeks = $this->estimateTimeline($property, $totalCost);

        // Generate risk factors
        $riskFactors = $this->generateRiskFactors($property, $propertyData);

        // Generate notes
        $notes = $this->generateCalculationNotes($property, $totalCost, $breakdown);

        // Create estimate record
        $estimate = PropertyRehabEstimate::create([
            'property_id' => $property->id,
            'requested_by' => $user?->id,
            'ai_response' => json_encode([
                'total_cost' => $totalCost,
                'breakdown' => $breakdown,
                'labor_percentage' => $laborPercentage,
                'materials_percentage' => $materialsPercentage,
                'timeline_weeks' => $timelineWeeks,
                'risk_factors' => $riskFactors,
                'notes' => $notes,
                'confidence' => $this->calculateConfidence($propertyData),
                'calculation_method' => 'formula-based',
            ]),
            'property_data' => $propertyData,
            'model_used' => 'calculation-fallback',
            'estimated_cost' => $totalCost,
            'tokens_used' => null,
        ]);

        // Cache the estimate
        $this->cacheEstimate($property, $estimate);

        return $estimate;
    }

    /**
     * Calculate base cost from property characteristics
     */
    protected function calculateBaseCost(Property $property, array $propertyData): float
    {
        $config = config('services.rehab_calculations', []);
        $costPerSqft = $config['cost_per_sqft'] ?? [];
        $bedroomMultiplier = $config['bedroom_multiplier'] ?? 2000;
        $bathroomMultiplier = $config['bathroom_multiplier'] ?? 5000;

        $propertyType = $property->property_type ?? 'house';
        $sqftCost = $costPerSqft[$propertyType] ?? $costPerSqft['house'] ?? 50;

        $baseCost = ($property->square_feet ?? 1500) * $sqftCost;
        $bedroomCost = ($property->bedrooms ?? 3) * $bedroomMultiplier;
        $bathroomCost = ($property->bathrooms ?? 2) * $bathroomMultiplier;

        return $baseCost + $bedroomCost + $bathroomCost;
    }

    /**
     * Calculate condition-based adjustment
     */
    protected function calculateConditionAdjustment(Property $property, float $baseCost): float
    {
        $config = config('services.rehab_calculations', []);
        $multipliers = $config['condition_multipliers'] ?? [];
        
        $condition = $property->condition ?? 'fair';
        $multiplier = $multipliers[$condition] ?? $multipliers['fair'] ?? 1.0;

        // Adjustment is the difference from base (1.0)
        return $baseCost * ($multiplier - 1.0);
    }

    /**
     * Calculate age-based adjustment
     */
    protected function calculateAgeAdjustment(Property $property, float $baseCost): float
    {
        $config = config('services.rehab_calculations', []);
        $adjustments = $config['age_adjustments'] ?? [];
        
        $yearBuilt = $property->year_built ?? 2000;
        $currentYear = (int) date('Y');
        $age = $currentYear - $yearBuilt;

        $ageCategory = 'average';
        if ($age < 15) {
            $ageCategory = 'new';
        } elseif ($age < 35) {
            $ageCategory = 'modern';
        } elseif ($age < 55) {
            $ageCategory = 'average';
        } elseif ($age < 75) {
            $ageCategory = 'old';
        } else {
            $ageCategory = 'very_old';
        }

        $multiplier = $adjustments[$ageCategory] ?? $adjustments['average'] ?? 1.0;

        // Adjustment is the difference from base (1.0)
        return $baseCost * ($multiplier - 1.0);
    }

    /**
     * Calculate regional cost adjustment
     */
    protected function calculateRegionalAdjustment(Property $property, float $baseCost): float
    {
        $config = config('services.rehab_calculations', []);
        $multipliers = $config['regional_multipliers'] ?? [];
        
        $state = $property->state ?? '';
        $multiplier = $multipliers[$state] ?? $multipliers['default'] ?? 1.0;

        // Adjustment is the difference from base (1.0)
        return $baseCost * ($multiplier - 1.0);
    }

    /**
     * Calculate image-based adjustment (heuristic)
     */
    protected function calculateImageAdjustment(array $propertyData, float $baseCost): float
    {
        $config = config('services.rehab_calculations', []);
        $heuristic = $config['image_heuristic'] ?? [];
        
        $imageCount = $propertyData['image_count'] ?? 0;
        $minImages = $heuristic['min_images_for_confidence'] ?? 5;
        $adjustmentPercent = $heuristic['confidence_adjustment'] ?? 0.1;

        // If property has many images, it's better documented, so reduce uncertainty (lower costs)
        if ($imageCount >= $minImages) {
            return $baseCost * $adjustmentPercent;
        }

        return 0;
    }

    /**
     * Generate cost breakdown by category
     */
    protected function generateCostBreakdown(float $totalCost, Property $property, array $propertyData): array
    {
        $config = config('services.rehab_calculations', []);
        $percentages = $config['breakdown_percentages'] ?? [];
        
        $breakdown = [];

        // Standard categories
        $breakdown['kitchen'] = (int) ($totalCost * ($percentages['kitchen'] ?? 25) / 100);
        $breakdown['bathrooms'] = (int) ($totalCost * ($percentages['bathrooms'] ?? 20) / 100);
        $breakdown['flooring'] = (int) ($totalCost * ($percentages['flooring'] ?? 12) / 100);
        $breakdown['paint'] = (int) ($totalCost * ($percentages['paint'] ?? 8) / 100);
        $breakdown['electrical'] = (int) ($totalCost * ($percentages['electrical'] ?? 10) / 100);
        $breakdown['plumbing'] = (int) ($totalCost * ($percentages['plumbing'] ?? 10) / 100);
        $breakdown['hvac'] = (int) ($totalCost * ($percentages['hvac'] ?? 8) / 100);

        // Conditional categories (only if condition indicates need)
        $condition = $property->condition ?? 'fair';
        if (in_array($condition, ['poor', 'needs_repair'])) {
            $breakdown['roof'] = (int) ($totalCost * 10 / 100);
            $breakdown['windows'] = (int) ($totalCost * 5 / 100);
        } else {
            $breakdown['roof'] = 0;
            $breakdown['windows'] = 0;
        }

        $breakdown['other'] = (int) ($totalCost * ($percentages['other'] ?? 7) / 100);

        // Adjust to match total (handle rounding)
        $currentTotal = array_sum($breakdown);
        if ($currentTotal !== $totalCost) {
            $difference = $totalCost - $currentTotal;
            $breakdown['other'] += (int) $difference;
        }

        return $breakdown;
    }

    /**
     * Estimate timeline in weeks
     */
    protected function estimateTimeline(Property $property, float $totalCost): int
    {
        $sqft = $property->square_feet ?? 1500;
        $condition = $property->condition ?? 'fair';

        // Base timeline: 1 week per $10,000 of work, minimum 4 weeks
        $baseWeeks = max(4, (int) ($totalCost / 10000));

        // Adjust for property size
        if ($sqft > 3000) {
            $baseWeeks += 2;
        } elseif ($sqft > 2000) {
            $baseWeeks += 1;
        }

        // Adjust for condition
        if (in_array($condition, ['poor', 'needs_repair'])) {
            $baseWeeks += 2;
        }

        return min($baseWeeks, 16); // Cap at 16 weeks
    }

    /**
     * Generate risk factors based on property characteristics
     */
    protected function generateRiskFactors(Property $property, array $propertyData): array
    {
        $risks = [];

        $yearBuilt = $property->year_built ?? 2000;
        $currentYear = (int) date('Y');
        $age = $currentYear - $yearBuilt;

        if ($age > 50) {
            $risks[] = "Older property (built {$yearBuilt}) may require structural or system updates";
        }

        $condition = $property->condition ?? 'fair';
        if (in_array($condition, ['poor', 'needs_repair'])) {
            $risks[] = "Property condition is {$condition} - may require extensive repairs";
        }

        $imageCount = $propertyData['image_count'] ?? 0;
        if ($imageCount < 3) {
            $risks[] = "Limited property documentation - estimate may not account for hidden issues";
        }

        if (empty($property->square_feet) || empty($property->bedrooms) || empty($property->bathrooms)) {
            $risks[] = "Incomplete property data may affect estimate accuracy";
        }

        return $risks;
    }

    /**
     * Generate notes for calculation-based estimate
     */
    protected function generateCalculationNotes(Property $property, float $totalCost, array $breakdown): string
    {
        $notes = "This estimate is based on formula calculations using property characteristics. ";
        $notes .= "Total estimated cost: $" . number_format($totalCost, 2) . ". ";
        
        $notes .= "Breakdown includes: ";
        $categories = [];
        foreach ($breakdown as $category => $amount) {
            if ($amount > 0) {
                $categories[] = ucfirst($category) . " ($" . number_format($amount) . ")";
            }
        }
        $notes .= implode(", ", $categories) . ". ";
        
        $notes .= "This is a formula-based estimate and should be verified with on-site inspection and contractor quotes.";

        return $notes;
    }

    /**
     * Calculate confidence level based on available data
     */
    protected function calculateConfidence(array $propertyData): string
    {
        $imageCount = $propertyData['image_count'] ?? 0;
        $hasCompleteData = !empty($propertyData['property']['square_feet']) 
            && !empty($propertyData['property']['bedrooms']) 
            && !empty($propertyData['property']['bathrooms']);

        if ($hasCompleteData && $imageCount >= 5) {
            return 'medium';
        } elseif ($hasCompleteData) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}

