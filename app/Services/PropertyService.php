<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyImage;
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
        
        // Calculate potential profit if ARV and repair estimate are provided
        if (isset($data['arv']) && isset($data['repair_estimate'])) {
            $data['potential_profit'] = $data['arv'] - $data['asking_price'] - $data['repair_estimate'];
        }

        $property = Property::create($data);

        return $property->load('wholesaler', 'images');
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

        $property->update($data);

        return $property->fresh()->load('wholesaler', 'images');
    }

    /**
     * Delete a property
     */
    public function delete(Property $property): bool
    {
        // Delete associated images from storage
        foreach ($property->images as $image) {
            if (Storage::exists($image->path)) {
                Storage::delete($image->path);
            }
        }

        return $property->delete();
    }

    /**
     * Upload images for a property
     */
    public function uploadImages(Property $property, array $images, ?int $primaryIndex = 0): array
    {
        $uploadedImages = [];

        foreach ($images as $index => $image) {
            if ($image instanceof UploadedFile) {
                $path = $this->storeImage($image, $property->id);
                $isPrimary = $index === $primaryIndex;

                $propertyImage = PropertyImage::create([
                    'property_id' => $property->id,
                    'path' => $path,
                    'url' => Storage::url($path),
                    'type' => 'image',
                    'order' => $index,
                    'is_primary' => $isPrimary,
                    'alt_text' => $property->title . ' - Image ' . ($index + 1),
                ]);

                $uploadedImages[] = $propertyImage;

                // If this is primary, unset other primary images
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
        if (Storage::exists($image->path)) {
            Storage::delete($image->path);
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
        $query = Property::with(['wholesaler', 'images']);

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
}

