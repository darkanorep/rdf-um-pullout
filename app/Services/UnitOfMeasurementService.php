<?php

namespace App\Services;

use App\Models\UnitOfMeasurement;

class UnitOfMeasurementService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly UnitOfMeasurement $unitOfMeasurement) {}

    public function getAllUnitOfMeasurements()
    {
        return $this->unitOfMeasurement->query()->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeUnitOfMeasurement(array $data): UnitOfMeasurement
    {
        return $this->unitOfMeasurement->create($data);
    }

    public function findById(int $id): ?UnitOfMeasurement
    {
        return $this->unitOfMeasurement->find($id);
    }

    public function updateUnitOfMeasurement(array $data, UnitOfMeasurement $unitOfMeasurement): UnitOfMeasurement {
        $unitOfMeasurement->update($data);
        return $unitOfMeasurement;
    }

    public function destroyUnitOfMeasurement(int $id): void
    {
        $unitOfMeasurement = $this->unitOfMeasurement->withTrashed()->find($id);
        if ($unitOfMeasurement->trashed()) {
            $unitOfMeasurement->restore();
        } else {
            $unitOfMeasurement->delete();
        }
    }
}
