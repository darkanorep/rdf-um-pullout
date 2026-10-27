<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnitOfMeasurementRequest;
use App\Http\Resources\UnitOfMeasurementResource;
use App\Models\UnitOfMeasurement;
use App\Services\UnitOfMeasurementService;
use Illuminate\Pagination\LengthAwarePaginator;

class UnitOfMeasurementController extends Controller
{
    public function __construct(private readonly UnitOfMeasurementService $unitOfMeasurementService) {}

    public function index()
    {
        $uoms = $this->unitOfMeasurementService->getAllUnitOfMeasurements();

        if ($uoms->isEmpty()) {
            return $this->responseNotFound(message: 'No UOMs found.');
        }

        return $uoms instanceof LengthAwarePaginator
            ? $uoms->through(fn($item) => new UnitOfMeasurementResource($item))
            : $this->responseSuccess('UOMs retrieved successfully.', UnitOfMeasurementResource::collection($uoms));
    }

    public function store(UnitOfMeasurementRequest $request) {
        $data = $request->validated();
        $item = $this->unitOfMeasurementService->storeUnitOfMeasurement($data);

        return $this->responseCreated(
            message: 'UOM created successfully.',
            data: new UnitOfMeasurementResource($item)
        );
    }

    public function show(int $id)
    {
        $uom = $this->unitOfMeasurementService->findById($id);

        if (! $uom) {
            return $this->responseNotFound(message: 'UOM not found.');
        }

        return $this->responseSuccess(
            message: 'UOM retrieved successfully.',
            data: new UnitOfMeasurementResource($uom)
        );
    }

    public function update(UnitOfMeasurementRequest $request, UnitOfMeasurement $unitOfMeasurement)
    {
        $data = $request->validated();

        $updatedUom = $this->unitOfMeasurementService->updateUnitOfMeasurement($data, $unitOfMeasurement);

        return $this->responseAccepted(
            message: 'UOM updated successfully.',
            data: new UnitOfMeasurementResource($updatedUom)
        );
    }

    public function destroy(int $id)
    {
        $this->unitOfMeasurementService->destroyUnitOfMeasurement($id);

        return $this->responseAccepted(
            message: 'UOM status updated successfully.'
        );
    }
}
