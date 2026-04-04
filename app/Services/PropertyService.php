<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyRehabEstimate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertyService
{
    /**
     * Create a new property
     */
    public function create(array $data, User $wholesaler): Property
    {
        $data['wholesaler_id'] = $wholesaler->id;
        
        // Set default country if not provided
        if (!isset($data['country'])) {
            $data['country'] = 'US';
        }
        
        // New properties start as draft/unpaid until publish payment is made
        $data['status'] = 'draft';
        $data['payment_status'] = 'unpaid';

        // Calculate potential profit if ARV and repair estimate are provided
        if (isset($data['arv']) && isset($data['repair_estimate'])) {
            $data['potential_profit'] = $data['arv'] - $data['asking_price'] - $data['repair_estimate'];
        }

        // Extract images and primary_image_index before creating property
        $images = $data['images'] ?? [];
        $primaryImageIndex = $data['primary_image_index'] ?? 0;
        unset($data['images'], $data['primary_image_index']);

        // Extract rehab estimate from preview (persist after property is created)
        $rehabEstimatePayload = $data['rehab_estimate'] ?? null;
        unset($data['rehab_estimate']);
        if (is_string($rehabEstimatePayload)) {
            $rehabEstimatePayload = json_decode($rehabEstimatePayload, true) ?: null;
        }

        // Extract building permits from frontend (passed through from ATTOM lookup/preview)
        $buildingPermits = $data['building_permits'] ?? null;
        unset($data['building_permits']);
        if (is_string($buildingPermits)) {
            $buildingPermits = json_decode($buildingPermits, true) ?: null;
        }

        // Store building permits as attom_property_events (from ATTOM /property/buildingpermits endpoint)
        if (!empty($buildingPermits) && is_array($buildingPermits)) {
            $data['attom_property_events'] = [
                'total_permits' => count($buildingPermits),
                'permits' => $buildingPermits,
            ];
        }

        $property = Property::create($data);

        // Upload images if provided
        if (!empty($images)) {
            $this->uploadImages($property, $images, $primaryImageIndex);
        }

        // Persist AI/calculation rehab estimate used during creation (from preview)
        if (!empty($rehabEstimatePayload) && isset($rehabEstimatePayload['estimated_cost'])) {
            $this->attachRehabEstimateFromPreview($property, $rehabEstimatePayload, $wholesaler);
        }

        return $property->load('wholesaler', 'images', 'latestRehabEstimate');
    }

    /**
     * Persist the rehab estimate used during property creation (from preview endpoint).
     * Creates a PropertyRehabEstimate and syncs property.repair_estimate.
     */
    protected function attachRehabEstimateFromPreview(Property $property, array $payload, User $user): void
    {
        $estimatedCost = (float) ($payload['estimated_cost'] ?? 0);
        $propertyData = $payload['property_data'] ?? null;
        $modelUsed = $payload['model_used'] ?? 'calculation-fallback';
        $tokensUsed = $payload['tokens_used'] ?? null;

        // Full estimate as stored by RehabEstimateService (breakdown, notes, confidence, etc.)
        $aiResponse = json_encode([
            'estimated_cost' => $estimatedCost,
            'breakdown' => $payload['breakdown'] ?? [],
            'labor_percentage' => $payload['labor_percentage'] ?? null,
            'materials_percentage' => $payload['materials_percentage'] ?? null,
            'timeline_weeks' => $payload['timeline_weeks'] ?? null,
            'risk_factors' => $payload['risk_factors'] ?? [],
            'notes' => $payload['notes'] ?? '',
            'confidence' => $payload['confidence'] ?? 'medium',
            'model_used' => $modelUsed,
        ]);

        PropertyRehabEstimate::create([
            'property_id' => $property->id,
            'requested_by' => $user->id,
            'ai_response' => $aiResponse,
            'property_data' => $propertyData,
            'model_used' => $modelUsed,
            'estimated_cost' => $estimatedCost,
            'tokens_used' => $tokensUsed,
        ]);

        // Sync single repair_estimate on property for display / potential_profit
        $property->update([
            'repair_estimate' => $estimatedCost,
            'potential_profit' => $property->arv && $property->asking_price
                ? $property->arv - $property->asking_price - $estimatedCost
                : $property->potential_profit,
        ]);
    }

    /**
     * Update a property
     */
    public function update(Property $property, array $data): Property
    {
        // Recalculate potential profit if financial fields changed
        if (isset($data['arv']) || isset($data['repair_estimate']) || isset($data['asking_price'])) {
            $arv = $data['arv'] ?? $property->arv;
            $askingPrice = $data['asking_price'] ?? $property->asking_price;
            $repairEstimate = $data['repair_estimate'] ?? $property->repair_estimate;
            
            if ($arv && $repairEstimate) {
                $data['potential_profit'] = $arv - $askingPrice - $repairEstimate;
            }
        }

        // Extract images and primary_image_index before updating property
        $images = $data['images'] ?? [];
        $primaryImageIndex = $data['primary_image_index'] ?? null;
        unset($data['images'], $data['primary_image_index']);

        // Extract building permits and store as attom_property_events
        $buildingPermits = $data['building_permits'] ?? null;
        unset($data['building_permits']);
        if (is_string($buildingPermits)) {
            $buildingPermits = json_decode($buildingPermits, true) ?: null;
        }
        if (!empty($buildingPermits) && is_array($buildingPermits)) {
            $data['attom_property_events'] = [
                'total_permits' => count($buildingPermits),
                'permits' => $buildingPermits,
            ];
        }

        $property->update($data);

        // Upload new images if provided (adds to existing images)
        if (!empty($images)) {
            // Calculate the starting order for new images (after existing images)
            // primaryImageIndex is relative to the new images array (0 = first new image)
            $existingImageCount = $property->images()->count();
            $newPrimaryOrder = $primaryImageIndex !== null 
                ? $existingImageCount + $primaryImageIndex 
                : null;
            
            $this->uploadImages($property, $images, $newPrimaryOrder);
        }

        return $property->fresh()->load('wholesaler', 'images');
    }

    /**
     * Delete a property
     */
    public function delete(Property $property): bool
    {
        // Delete associated images from storage and database
        foreach ($property->images as $image) {
            if (Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
            $image->delete(); // Delete from database (cascade doesn't work with soft deletes)
        }

        return $property->delete();
    }

    /**
     * Upload images for a property
     * 
     * Images are ordered by insertion order (0, 1, 2, ...)
     * When updating, new images continue from existing count
     * 
     * @param Property $property The property to upload images to
     * @param array $images Array of UploadedFile instances
     * @param int|null $primaryOrder The order number that should be primary (null = auto-select first if no images exist)
     * @return array Array of created PropertyImage models
     */
    public function uploadImages(Property $property, array $images, ?int $primaryOrder = null): array
    {
        $uploadedImages = [];
        $existingImageCount = $property->images()->count();

        foreach ($images as $index => $image) {
            if ($image instanceof UploadedFile) {
                $path = $this->storeImage($image, $property->id);
                // Order is based on insertion order: existing count + current index
                $order = $existingImageCount + $index;
                
                // Determine if this image should be primary
                // - If primaryOrder is specified, use it (absolute order number)
                // - If primaryOrder is null and no images exist, first image (index 0) is primary
                // - If primaryOrder is null and images exist, none of the new images are primary
                $isPrimary = ($primaryOrder !== null && $order === $primaryOrder) 
                    || ($primaryOrder === null && $existingImageCount === 0 && $index === 0);

                $propertyImage = PropertyImage::create([
                    'property_id' => $property->id,
                    'path' => $path,
                    'url' => asset('storage/' . $path), // Requires storage:link symlink
                    'type' => 'image',
                    'order' => $order, // Maintains insertion order
                    'is_primary' => $isPrimary,
                    'alt_text' => $property->title . ' - Image ' . ($order + 1),
                ]);

                $uploadedImages[] = $propertyImage;

                // If this is primary, unset all other primary images for this property
                if ($isPrimary) {
                    PropertyImage::where('property_id', $property->id)
                        ->where('id', '!=', $propertyImage->id)
                        ->update(['is_primary' => false]);
                }
            }
        }

        return $uploadedImages;
    }

    /**
     * Store an uploaded image
     */
    protected function storeImage(UploadedFile $file, string $propertyId): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = "properties/{$propertyId}/{$filename}";

        $file->storeAs("properties/{$propertyId}", $filename, 'public');

        return $path;
    }

    /**
     * Delete an image
     */
    public function deleteImage(PropertyImage $image): bool
    {
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        return $image->delete();
    }

    /**
     * Set primary image
     */
    public function setPrimaryImage(Property $property, PropertyImage $image): void
    {
        // Unset all other primary images
        PropertyImage::where('property_id', $property->id)
            ->where('id', '!=', $image->id)
            ->update(['is_primary' => false]);

        // Set this image as primary
        $image->update(['is_primary' => true]);
    }

    /**
     * Get properties with filters
     */
    public function getFiltered(array $filters = [], int $perPage = 15)
    {
        $query = Property::with(['wholesaler', 'images', 'latestRehabEstimate']);

        // Filter by city
        if (isset($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        // Filter by state
        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        // Filter by property type
        if (isset($filters['property_type'])) {
            $query->where('property_type', $filters['property_type']);
        }

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by price range
        if (isset($filters['min_price'])) {
            $query->where('asking_price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('asking_price', '<=', $filters['max_price']);
        }

        // Filter by bedrooms
        if (isset($filters['bedrooms'])) {
            $query->where('bedrooms', '>=', $filters['bedrooms']);
        }

        // Filter by bathrooms
        if (isset($filters['bathrooms'])) {
            $query->where('bathrooms', '>=', $filters['bathrooms']);
        }

        // Filter by featured
        if (isset($filters['featured']) && $filters['featured']) {
            $query->featured();
        }

        // Filter by verified
        if (isset($filters['verified']) && $filters['verified']) {
            $query->verified();
        }

        // Search
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find similar properties to a given property
     * 
     * Similarity is based on:
     * - Same city and state (location)
     * - Same property type
     * - Similar price range (±20%)
     * - Similar bedrooms (±1)
     * - Similar bathrooms (±0.5)
     * - Similar square feet (±15%)
     */
    public function findSimilarProperties(Property $property, int $limit = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Property::with(['wholesaler', 'images', 'primaryImage'])
            ->where('id', '!=', $property->id) // Exclude the property itself
            ->where('status', 'active'); // Only active properties

        // Location match (same city and state)
        if ($property->city && $property->state) {
            $query->where('city', $property->city)
                  ->where('state', $property->state);
        } elseif ($property->state) {
            // If no city, at least match state
            $query->where('state', $property->state);
        }

        // Property type match
        if ($property->property_type) {
            $query->where('property_type', $property->property_type);
        }

        // Price range (±20%)
        if ($property->asking_price) {
            $priceRange = $property->asking_price * 0.20; // 20% range
            $minPrice = $property->asking_price - $priceRange;
            $maxPrice = $property->asking_price + $priceRange;
            
            $query->where('asking_price', '>=', $minPrice)
                  ->where('asking_price', '<=', $maxPrice);
        }

        // Bedrooms match (±1)
        if ($property->bedrooms !== null) {
            $query->whereBetween('bedrooms', [
                max(0, $property->bedrooms - 1),
                $property->bedrooms + 1
            ]);
        }

        // Bathrooms match (±0.5)
        if ($property->bathrooms !== null) {
            $query->whereBetween('bathrooms', [
                max(0, $property->bathrooms - 0.5),
                $property->bathrooms + 0.5
            ]);
        }

        // Square feet match (±15%)
        if ($property->square_feet) {
            $sqftRange = $property->square_feet * 0.15; // 15% range
            $minSqft = max(0, $property->square_feet - $sqftRange);
            $maxSqft = $property->square_feet + $sqftRange;
            
            $query->where('square_feet', '>=', $minSqft)
                  ->where('square_feet', '<=', $maxSqft);
        }

        // Order by relevance (prioritize exact matches)
        // We can add a scoring system later, for now just order by created_at
        $query->orderBy('created_at', 'desc');

        return $query->paginate($limit);
    }
}

