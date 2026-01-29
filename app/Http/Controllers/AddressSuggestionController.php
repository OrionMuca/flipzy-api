<?php

namespace App\Http\Controllers;

use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressSuggestionController extends Controller
{
    public function __construct(
        protected GeoService $geoService
    ) {}

    /**
     * Return address suggestions for autocomplete (wholesaler: when adding a property).
     * Uses Mapbox when MAPBOX_ACCESS_TOKEN is set, otherwise OpenStreetMap Nominatim.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('wholesaler') && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only wholesalers can use address suggestions.',
            ], 403);
        }

        $request->validate([
            'q' => 'required|string|min:2|max:500',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $query = $request->input('q');
        $limit = (int) $request->input('limit', 8);

        $suggestions = $this->geoService->getAddressSuggestions($query, $limit);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }
}
