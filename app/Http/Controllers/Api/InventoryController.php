<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Strain;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index()
    {
        $strains = Strain::all();
        $data = $strains->map(function ($strain) {
            return [
                'id' => $strain->id,
                'name' => $strain->name,
                'available_stock' => $this->inventoryService->getAvailableStock($strain->id),
                'incubation_days' => $strain->incubation_days,
            ];
        });

        return response()->json($data);
    }

    public function show(int $id)
    {
        $strain = Strain::findOrFail($id);

        return response()->json([
            'id' => $strain->id,
            'name' => $strain->name,
            'available_stock' => $this->inventoryService->getAvailableStock($strain->id),
            'incubation_days' => $strain->incubation_days,
        ]);
    }
    public function publicAvailability()
    {
        $strains = Strain::all();
        // Return only safe data: Name, Has Stock (bool), Next Harvest Date
        $data = $strains->map(function ($strain) {
            $stock = $this->inventoryService->getAvailableStock($strain->id);
            $nextHarvest = $this->inventoryService->getNextHarvestDate($strain->id);

            return [
                'name' => $strain->name,
                'has_stock' => $stock > 0,
                'next_harvest_date' => $nextHarvest,
            ];
        });

        return response()->json($data);
    }
}
