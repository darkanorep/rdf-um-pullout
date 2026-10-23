<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChargeResource;
use App\Services\ChargeService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ChargeController extends Controller
{
    public function __construct(private readonly ChargeService $chargeService) {}

    public function sync() {
        try {
            $this->chargeService->syncCharges();
            return $this->responseSuccess('Charges synchronized successfully.');
        } catch (\Exception $e) {
            return $this->responseWithCustomError('Error', 'Failed to synchronize charges: ' . $e->getMessage(), 500);
        }
    }

    public function index()
    {
        $charges = $this->chargeService->getAllCharges();

        if (empty($charges->items())) {
            return $this->responseNotFound(message: 'No charges found.');
        }

        return $charges instanceof LengthAwarePaginator
            ? $charges->through(fn($item) => new ChargeResource($item))
            : $this->responseSuccess('Charges retrieved successfully.', ChargeResource::collection($charges));
    }
}
