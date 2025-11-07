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
        
        // Set default country if not provided
        if (!isset($data['country'])) {
            $data['country'] = 'US';
        }
        
        // Calculate potential profit if ARV and repair estimate are provided
        if (isset($data['arv']) && isset($data['repair_estimate'])) {
            $data['potential_profit'] = $data['arv'] - $data['asking_price'] - $data['repair_estimate'];
        }

        // Extract images and primary_image_index before creating property
        $images = $data['images'] ?? [];
        $primaryImageIndex = $data['primary_image_index'] ?? 0;
        unset($data['images'], $data['primary_image_index']);

        $property = Property::create($data);

        // Upload images if provided
        if (!empty($images)) {
            $this->uploadImages($property, $images, $primaryImageIndex);
        }

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

        // Extract images and primary_image_index before updating property
        $images = $data['images'] ?? [];
        $primaryImageIndex = $data['primary_image_index'] ?? null;
        unset($data['images'], $data['primary_image_index']);

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

