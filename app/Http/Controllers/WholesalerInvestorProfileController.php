<?php

namespace App\Http\Controllers;

use App\Http\Resources\WholesalerInvestorProfileResource;
use App\Models\WholesalerInvestorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class WholesalerInvestorProfileController extends Controller
{
    /**
     * List wholesaler investor profiles for the authenticated wholesaler.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->hasRole('wholesaler') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only wholesalers can manage investor profiles.',
            ], 403);
        }

        $perPage = (int) $request->get('per_page', 15);

        $query = WholesalerInvestorProfile::query();

        // Admin can see all profiles; wholesalers only see their own
        if ($user->hasRole('wholesaler') && !$user->hasRole('admin')) {
            $query->where('wholesaler_id', $user->id);
        }

        $profiles = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WholesalerInvestorProfileResource::collection($profiles),
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
            ],
        ]);
    }

    /**
     * Store a new investor profile manually.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->hasRole('wholesaler') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only wholesalers can manage investor profiles.',
            ], 403);
        }

        $data = $this->validateProfileData($request);

        $profile = WholesalerInvestorProfile::create(array_merge(
            $data,
            ['wholesaler_id' => $user->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Investor profile created successfully',
            'data' => new WholesalerInvestorProfileResource($profile),
        ], 201);
    }

    /**
     * Show a single investor profile.
     */
    public function show(Request $request, WholesalerInvestorProfile $profile): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->hasRole('admin') && $profile->wholesaler_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You cannot view this investor profile.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new WholesalerInvestorProfileResource($profile),
        ]);
    }

    /**
     * Update an existing investor profile.
     */
    public function update(Request $request, WholesalerInvestorProfile $profile): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->hasRole('admin') && $profile->wholesaler_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You cannot update this investor profile.',
            ], 403);
        }

        $data = $this->validateProfileData($request, partial: true);

        $profile->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Investor profile updated successfully',
            'data' => new WholesalerInvestorProfileResource($profile->fresh()),
        ]);
    }

    /**
     * Delete an investor profile.
     */
    public function destroy(Request $request, WholesalerInvestorProfile $profile): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->hasRole('admin') && $profile->wholesaler_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You cannot delete this investor profile.',
            ], 403);
        }

        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Investor profile deleted successfully',
        ]);
    }

    /**
     * Import investor profiles from an uploaded Excel/CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$user->hasRole('wholesaler') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only wholesalers can import investor profiles.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
            'mode' => 'nullable|string|in:append,replace_all',
        ]);

        $mode = $request->get('mode', 'append');

        try {
            $collection = Excel::toCollection(new class {
            }, $request->file('file'))->first();
            if (!$collection || $collection->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded file is empty.',
                ], 422);
            }

            $header = $collection->first();
            $rows = $collection->slice(1);

            if ($mode === 'replace_all') {
                WholesalerInvestorProfile::where('wholesaler_id', $user->id)->delete();
            }

            $createdCount = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowData = $this->mapRowToProfileData($header, $row);

                // Skip empty name rows
                if (empty($rowData['name'])) {
                    continue;
                }

                $validator = Validator::make($rowData, $this->profileRules(partial: false));

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $index + 2, // +2 for header and 1-based indexing
                        'errors' => $validator->errors()->all(),
                    ];
                    continue;
                }

                WholesalerInvestorProfile::create(array_merge(
                    $rowData,
                    ['wholesaler_id' => $user->id]
                ));

                $createdCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Investor profiles imported successfully',
                'data' => [
                    'created' => $createdCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Investor profile import failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import investor profiles',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Validate profile data from request.
     */
    protected function validateProfileData(Request $request, bool $partial = false): array
    {
        $rules = $this->profileRules($partial);

        return $request->validate($rules);
    }

    /**
     * Base validation rules for a profile.
     */
    protected function profileRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $nullableArray = $partial ? 'sometimes|array' : 'nullable|array';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'notes' => 'nullable|string',

            'preferred_cities' => $nullableArray,
            'preferred_cities.*' => 'string|max:100',
            'preferred_zip_codes' => $nullableArray,
            'preferred_zip_codes.*' => 'string|max:10',
            'target_counties' => $nullableArray,
            'target_counties.*' => 'string|max:100',
            'target_neighborhoods' => $nullableArray,
            'target_neighborhoods.*' => 'string|max:100',
            'must_have_amenities' => $nullableArray,
            'must_have_amenities.*' => 'string|max:255',

            'min_bedrooms' => 'nullable|integer|min:0|max:50',
            'max_bedrooms' => 'nullable|integer|min:0|max:50',
            'min_bathrooms' => 'nullable|numeric|min:0|max:50',
            'max_bathrooms' => 'nullable|numeric|min:0|max:50',
            'min_square_feet' => 'nullable|integer|min:0',
            'max_square_feet' => 'nullable|integer|min:0',
            'min_lot_size' => 'nullable|integer|min:0',
            'max_lot_size' => 'nullable|integer|min:0',

            'property_conditions' => $nullableArray,
            'property_conditions.*' => 'string|max:100',

            'property_types' => $nullableArray,
            'property_types.*' => 'string|max:100',

            'has_adu_potential' => 'nullable|boolean',

            'construction_types' => $nullableArray,
            'construction_types.*' => 'string|max:100',

            'has_pool' => 'nullable|boolean',
            'is_waterfront' => 'nullable|boolean',

            'layout_types' => $nullableArray,
            'layout_types.*' => 'string|max:100',

            'funding_methods' => $nullableArray,
            'funding_methods.*' => 'string|max:100',

            'min_profit' => 'nullable|numeric|min:0',
            'min_roi' => 'nullable|numeric|min:0|max:100',
            'target_cap_rate' => 'nullable|numeric|min:0|max:100',
            'desired_occupancy_rate' => 'nullable|numeric|min:0|max:100',
            'expected_monthly_cash_flow' => 'nullable|numeric',
            'expected_annual_cash_flow' => 'nullable|numeric',

            'investment_strategies' => $nullableArray,
            'investment_strategies.*' => 'string|max:100',
        ];
    }

    /**
     * Map a spreadsheet row to profile data using the header row.
     *
     * Expects header names that match the column names in the database.
     */
    protected function mapRowToProfileData($header, $row): array
    {
        $data = [];

        foreach ($header as $index => $columnName) {
            $key = trim((string) $columnName);
            if ($key === '') {
                continue;
            }

            $value = $row[$index] ?? null;

            if ($value === null || $value === '') {
                $data[$key] = null;
                continue;
            }

            // For JSON/array fields we support either JSON string or pipe-separated string
            $arrayFields = [
                'preferred_cities',
                'preferred_zip_codes',
                'target_counties',
                'target_neighborhoods',
                'must_have_amenities',
                'property_conditions',
                'property_types',
                'construction_types',
                'layout_types',
                'funding_methods',
                'investment_strategies',
            ];

            if (in_array($key, $arrayFields, true)) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data[$key] = $decoded;
                    } else {
                        $data[$key] = array_values(array_filter(array_map('trim', explode('|', $value))));
                    }
                } elseif (is_array($value)) {
                    $data[$key] = $value;
                } else {
                    $data[$key] = [$value];
                }
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}

