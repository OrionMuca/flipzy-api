<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('wholesaler') || $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'property_type' => 'nullable|string|in:house,condo,townhouse,duplex,multi-family',
            'status' => 'nullable|string|in:active,pending,sold,inactive',
            
            // Address
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'zip_code' => 'required|string|max:10',
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
            'asking_price' => 'required|numeric|min:0',
            'arv' => 'nullable|numeric|min:0',
            'repair_estimate' => 'nullable|numeric|min:0',
            
            // Flags
            'is_featured' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'allow_inquiries' => 'nullable|boolean',
            
            // Images
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per image
            'primary_image_index' => 'nullable|integer|min:0',

            // Rehab estimate from preview (optional; stored as PropertyRehabEstimate). May be array or JSON string (multipart).
            'rehab_estimate' => 'nullable',
            'rehab_estimate.estimated_cost' => 'nullable|numeric|min:0',
            'rehab_estimate.breakdown' => 'nullable|array',
            'rehab_estimate.model_used' => 'nullable|string|max:64',
            'rehab_estimate.property_data' => 'nullable|array',
            'rehab_estimate.notes' => 'nullable|string',
            'rehab_estimate.tokens_used' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Property title is required.',
            'address.required' => 'Property address is required.',
            'city.required' => 'City is required.',
            'state.required' => 'State is required.',
            'state.size' => 'State must be a 2-letter code.',
            'zip_code.required' => 'ZIP code is required.',
            'asking_price.required' => 'Asking price is required.',
            'asking_price.numeric' => 'Asking price must be a number.',
            'asking_price.min' => 'Asking price must be greater than 0.',
        ];
    }
}
