<?php

namespace App\Http\Requests;

use App\Support\PropertyAddressUniqueness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Flatten the "details" object into top-level fields so the frontend can
     * send either flat fields or a nested details object (or both).
     * Top-level values take precedence over values inside details.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('details') && is_array($this->input('details'))) {
            $details = $this->input('details');

            // Only merge detail fields that are NOT already present at the top level
            $detailKeys = [
                'bedrooms', 'bathrooms', 'square_feet', 'lot_size', 'year_built',
                'condition', 'living_size', 'gross_size', 'zoning_type', 'pool_type',
                'municipality', 'legal1', 'cooling_type', 'heating_fuel', 'heating_type',
                'last_sale_date', 'tax_amount', 'tax_year',
            ];

            $merged = [];
            foreach ($detailKeys as $key) {
                if (array_key_exists($key, $details) && ! $this->has($key)) {
                    $merged[$key] = $details[$key];
                }
            }

            if (! empty($merged)) {
                $this->merge($merged);
            }
        }
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
            // status is set automatically to 'draft' — not user-controllable on create

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
            'year_built' => 'nullable|integer|min:1800|max:'.(date('Y') + 1),
            'condition' => 'nullable|string|in:excellent,good,fair,poor',

            // Extended property details (from ATTOM enrichment or frontend)
            'living_size' => 'nullable|integer|min:0',
            'gross_size' => 'nullable|integer|min:0',
            'zoning_type' => 'nullable|string|max:100',
            'pool_type' => 'nullable|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'legal1' => 'nullable|string|max:255',
            'cooling_type' => 'nullable|string|max:100',
            'heating_fuel' => 'nullable|string|max:100',
            'heating_type' => 'nullable|string|max:100',
            'last_sale_date' => 'nullable|date',
            'tax_amount' => 'nullable|numeric|min:0',
            'tax_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),

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

            // Building permits from ATTOM (passed through from lookup/preview). May be array or JSON string (multipart).
            'building_permits' => 'nullable',
            'building_permits.*' => 'nullable|array',
            'building_permits.*.effective_date' => 'nullable|string',
            'building_permits.*.permit_number' => 'nullable|string',
            'building_permits.*.status' => 'nullable|string',
            'building_permits.*.description' => 'nullable|string',
            'building_permits.*.type' => 'nullable|string',
            'building_permits.*.project_name' => 'nullable|string',
            'building_permits.*.job_value' => 'nullable|numeric',
            'building_permits.*.fees' => 'nullable|numeric',
            'building_permits.*.business_name' => 'nullable|string',
            'building_permits.*.home_owner_name' => 'nullable|string',
            'building_permits.*.classifiers' => 'nullable|array',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $conflictId = PropertyAddressUniqueness::findConflictId(
                (string) $this->input('address'),
                (string) $this->input('city'),
                (string) $this->input('state'),
                (string) $this->input('zip_code'),
            );

            if ($conflictId !== null) {
                $validator->errors()->add(
                    'address',
                    'A property with this address is already registered.'
                );
            }
        });
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
