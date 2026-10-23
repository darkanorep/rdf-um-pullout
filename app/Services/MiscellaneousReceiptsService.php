<?php

namespace App\Services;

use App\Models\MiscellaneousInventory;
use App\Models\MiscellaneousReceipt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MiscellaneousReceiptsService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly MiscellaneousReceipt $miscellaneousReceipt) {}

    public function getMiscellaneousReceipts()
    {
        return $this->miscellaneousReceipt->query()
            ->with(['miscellaneousInventory'])
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeMiscellaneousReceipt(array $data): MiscellaneousReceipt
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $stock = $this->miscellaneousReceipt->create(Arr::except($data, 'miscellaneous_inventory'));

                    $stock->miscellaneousInventory()->createMany($data['miscellaneous_inventory']);

                    return $stock->load('miscellaneousInventory');
                });
            } catch (QueryException $exception) {
                if ( $attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create receipt.');
    }

    public function findById(int $id): ?MiscellaneousReceipt
    {
        return $this->miscellaneousReceipt->with(['miscellaneousInventory'])->find($id);
    }

    public function updateMiscellaneousReceipt(array $data, MiscellaneousReceipt $miscellaneousReceipt): MiscellaneousReceipt
    {
        return DB::transaction(function () use ($data, $miscellaneousReceipt) {
            // Serialize concurrent edits of the same stock
            $stock = $this->miscellaneousReceipt->whereKey($miscellaneousReceipt->getKey())->lockForUpdate()->firstOrFail();

            $stock->update(Arr::except($data, 'miscellaneous_inventory'));

            // Only touch lines if the client sent them (supports header-only PATCH)
            if (array_key_exists('miscellaneous_inventory', $data)) {
                $this->syncInventories($miscellaneousReceipt, $data['miscellaneous_inventory']);
            }

            return $miscellaneousReceipt->load('miscellaneousInventory');
        });
    }

    private function syncInventories(MiscellaneousReceipt $stock, array $lines): void
    {
        // Scoped to THIS stock, so a client can't hijack another stock's line by passing its id
        $existing = $stock->miscellaneousInventory()->get()->keyBy('id');
        $keepIds = [];

        foreach ($lines as $line) {
            $id = $line['id'] ?? null;

            if ($id === null) {
                $keepIds[] = $stock->miscellaneousInventory()->create($line)->id;

                continue;
            }

            $inventory = $existing->get($id)
                ?? throw (new ModelNotFoundException)->setModel(MiscellaneousInventory::class, [$id]);

            $inventory->update(Arr::except($line, 'id'));
            $keepIds[] = $inventory->id;
        }

        // Soft-delete lines the client removed
        $stock->miscellaneousInventory()->whereNotIn('id', $keepIds)->delete();
    }

    public function deleteMiscellaneousReceipt(int $id): void
    {
        $receipt = $this->miscellaneousReceipt->withTrashed()->find($id);
        if ($receipt->trashed()) {
            $receipt->restore();
        } else {
            $receipt->delete();
        }
    }
}
