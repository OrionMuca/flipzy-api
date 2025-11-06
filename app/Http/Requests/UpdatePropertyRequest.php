<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');
        $user = $this->user();

        // Admin can update any property
        if ($user->hasRole('admin')) {
            return true;
        }

        // Wholesaler can only update their own properties
        return $user->hasRole('wholesaler') && $property->wholesaler_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'property_type' => 'nullable|string|in:house,condo,townhouse,duplex,multi-family',
            'status' => 'nullable|string|in:active,pending,sold,inactive',
            
            // Address
            'address' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|size:2',
            'zip_code' => 'sometimes|required|string|max:10',
            'country' => 'nullable|string|size:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            
            // Property Details
            'bedrooms' => 'nullable|integer|min:0|max:20',
            'bathrooms' => 'nullable|numeric|min:0|max:20',
            'square_feet' => 'nullable|integer|min:0',
            'lot_size' => 'nullable|integer|min:0',
            'year_built' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'condition' => 'nullable|string|in:excellent,good,fair,poor',
            
            // Financial
            'asking_price' => 'sometimes|required|numeric|min:0',
            'arv' => 'nullable|numeric|min:0',
            'repair_estimate' => 'nullable|numeric|min:0',
            
            // Flags
            'is_featured' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'allow_inquiries' => 'nullable|boolean',
        ];
    }
}
