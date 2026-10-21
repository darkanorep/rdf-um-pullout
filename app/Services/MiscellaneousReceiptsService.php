<?php

namespace App\Services;

use App\Models\MiscellaneousReceipt;

class MiscellaneousReceiptsService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly MiscellaneousReceipt $miscellaneousReceipt) {}

    public function getMiscellaneousReceipts()
    {
        return $this->miscellaneousReceipt
            ->with('stocks.inventories')
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }
    public function storeMiscellaneousReceipt(array $data): MiscellaneousReceipt
    {
        return $this->miscellaneousReceipt->create($data);
    }

    public function findById(int $id): ?MiscellaneousReceipt
    {
        return $this
            ->with('stocks.inventories')
            ->miscellaneousReceipt->find($id);
    }

    public function updateMiscellaneousReceipt(array $data, MiscellaneousReceipt $miscellaneousReceipt): MiscellaneousReceipt
    {
        $miscellaneousReceipt->update($data);
        return $miscellaneousReceipt;
    }

    public function destroyMiscellaneousReceipt(int $id): void
    {
        $role = $this->miscellaneousReceipt->withTrashed()->find($id);
        if ($role->trashed()) {
            $role->restore();
        } else {
            $role->delete();
        }
    }
}
