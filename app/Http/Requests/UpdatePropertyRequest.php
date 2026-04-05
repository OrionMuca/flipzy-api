<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Support\PropertyAddressUniqueness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * Flatten the "details" object into top-level fields so the frontend can
     * send either flat fields or a nested details object (or both).
     * Top-level values take precedence over values inside details.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('details') && is_array($this->input('details'))) {
            $details = $this->input('details');

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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'property_type' => 'nullable|string|in:house,condo,townhouse,duplex,multi-family',
            // status changes (publish/unpublish) are handled via dedicated endpoints
            'status' => 'nullable|string|in:pending,sold,inactive',

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
            'asking_price' => 'sometimes|required|numeric|min:0',
            'arv' => 'nullable|numeric|min:0',
            'repair_estimate' => 'nullable|numeric|min:0',

            // Flags
            'is_featured' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'allow_inquiries' => 'nullable|boolean',

            // Images (for adding new images during update)
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max per image
            'primary_image_index' => 'nullable|integer|min:0',

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

            $touchesAddress = false;
            foreach (['address', 'city', 'state', 'zip_code'] as $key) {
                if ($this->has($key)) {
                    $touchesAddress = true;
                    break;
                }
            }

            if (! $touchesAddress) {
                return;
            }

            /** @var Property $property */
            $property = $this->route('property');

            $address = $this->input('address', $property->address);
            $city = $this->input('city', $property->city);
            $state = $this->input('state', $property->state);
            $zip = $this->input('zip_code', $property->zip_code);

            if ($address === null || $city === null || $state === null || $zip === null) {
                return;
            }

            $conflictId = PropertyAddressUniqueness::findConflictId(
                (string) $address,
                (string) $city,
                (string) $state,
                (string) $zip,
                $property->id,
            );

            if ($conflictId !== null) {
                $validator->errors()->add(
                    'address',
                    'A property with this address is already registered.'
                );
            }
        });
    }
}
