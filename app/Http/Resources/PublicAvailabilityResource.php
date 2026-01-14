<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resolve Service
        $service = app(\App\Services\InventoryService::class);

        $stock = $service->getAvailableStock($this->id);
        $nextHarvest = $service->getNextHarvestDate($this->id);

        // Determine Status
        $status = 'Agotado'; // Default

        if ($stock > 0) {
            $status = 'Disponible';
        } elseif ($nextHarvest) {
            $status = 'Preventa';
        }

        return [
            'strain_name' => $this->name,
            'status' => $status,
            'estimated_date' => $nextHarvest,
        ];
    }
}
