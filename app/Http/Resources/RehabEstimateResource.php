<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RehabEstimateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $aiResponse = $this->ai_response;
        $parsedResponse = null;

        // Try to parse AI response as JSON
        if (is_string($aiResponse)) {
            $parsedResponse = json_decode($aiResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $parsedResponse = null;
            }
        } elseif (is_array($aiResponse)) {
            $parsedResponse = $aiResponse;
        }

        // Extract breakdown from parsed response
        $breakdown = $parsedResponse['breakdown'] ?? [];
        $totalCost = $parsedResponse['total_cost'] ?? $this->estimated_cost;

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'property' => $this->when(
                $this->relationLoaded('property'),
                fn() => new PropertyResource($this->property)
            ),
            'requested_by' => $this->when(
                $this->relationLoaded('requestedBy'),
                fn() => $this->requestedBy ? new UserResource($this->requestedBy) : null
            ),
            'estimated_cost' => $this->estimated_cost ? (float) $this->estimated_cost : null,
            'total_cost' => $totalCost ? (float) $totalCost : null,
            'breakdown' => $breakdown,
            'labor_percentage' => $parsedResponse['labor_percentage'] ?? null,
            'materials_percentage' => $parsedResponse['materials_percentage'] ?? null,
            'timeline_weeks' => $parsedResponse['timeline_weeks'] ?? null,
            'risk_factors' => $parsedResponse['risk_factors'] ?? [],
            'notes' => $parsedResponse['notes'] ?? null,
            'confidence' => $parsedResponse['confidence'] ?? 'medium',
            'model_used' => $this->model_used,
            'tokens_used' => $this->tokens_used,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
