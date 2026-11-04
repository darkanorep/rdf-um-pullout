<?php

namespace App\Services;

use App\Models\Miscellaneous;
use App\Models\MiscellaneousInventory;
use App\Models\MiscellaneousIssue;
use App\Models\MiscellaneousReceipt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MiscellaneousService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly Miscellaneous $miscellaneous) {}

    public function getMiscellaneous()
    {
        return $this->miscellaneous->query()
            ->with(['miscellaneousIssue'])
            ->where('user_id', auth()->id())
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeMiscellaneous(array $data): Miscellaneous
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $stock = $this->miscellaneous->create(
                        array_merge(
                            Arr::except($data, 'miscellaneous_issue'),
                            ['user_id' => auth()->id()]
                        )
                    );

                    $stock->miscellaneousIssue()->createMany(
                        $data['miscellaneous_issue']
                    );

                    return $stock->load('miscellaneousIssue');
                });
            } catch (QueryException $exception) {
                if ($attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create stock.');
    }

    public function findById(int $id): ?Miscellaneous
    {
        return $this->miscellaneous->with(['miscellaneousIssue'])->find($id);
    }

    public function updateMiscellaneous(
        array $data,
        Miscellaneous $miscellaneous
    ): Miscellaneous {
        return DB::transaction(function () use ($data, $miscellaneous) {
            // Serialize concurrent edits of the same stock
            $stock = $this->miscellaneous
                ->whereKey($miscellaneous->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $stock->update(
                array_merge(
                    Arr::except($data, 'miscellaneous_issue'),
                    ['user_id' => auth()->id()]
                )
            );

            // Only touch lines if the client sent them
            if (array_key_exists('miscellaneous_issue', $data)) {
                $this->syncInventories(
                    $stock,
                    $data['miscellaneous_issue']
                );
            }

            return $stock->load('miscellaneousIssue');
        });
    }

    private function syncInventories(
        Miscellaneous $stock,
        array $lines
    ): void {
        // Scoped to THIS stock
        $existing = $stock->miscellaneousIssue()
            ->get()
            ->keyBy('id');

        $keepIds = [];

        foreach ($lines as $line) {
            $id = $line['id'] ?? null;

            if ($id === null) {
                $line['user_id'] = auth()->id();

                $keepIds[] = $stock->miscellaneousIssue()
                    ->create($line)->id;

                continue;
            }

            $inventory = $existing->get($id)
                ?? throw (new ModelNotFoundException)
                    ->setModel(MiscellaneousIssue::class, [$id]);

            $inventory->update(Arr::except($line, 'id'));

            $keepIds[] = $inventory->id;
        }

        // Soft-delete lines the client removed
        $stock->miscellaneousIssue()
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    public function deleteMiscellaneousReceipt(int $id): void
    {
        $receipt = $this->miscellaneous->withTrashed()->find($id);
        if ($receipt->trashed()) {
            $receipt->restore();
        } else {
            $receipt->delete();
        }
    }
}
