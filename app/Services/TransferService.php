<?php

namespace App\Services;

use App\Models\BondedItem;
use App\Models\BondedWarehouse;
use App\Models\Inventory;
use App\Models\Stock;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function listForUser(
        User $user,
        ?string $status = null,
        string $direction = 'all',
    ): LengthAwarePaginator {
        $query = BondedWarehouse::query()->with([
            'sender:id,first_name,last_name',
            'recipient:id,first_name,last_name',
            'items.inventory:id,stock_id,item_id,uom_id,account_title_id',
            'transfer.items.receivedInventory',
        ]);

        if ($direction === 'incoming') {
            $query->where('transferred_to', $user->getKey());
        } elseif ($direction === 'outgoing') {
            $query->where('transferred_by', $user->getKey());
        } else {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('transferred_by', $user->getKey())
                    ->orWhere('transferred_to', $user->getKey());
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    public function requestTransfer(
        User $sender,
        int $recipientId,
        array $items,
        ?string $remarks = null,
    ): BondedWarehouse {
        if ((int) $sender->getKey() === $recipientId) {
            throw ValidationException::withMessages([
                'transferred_to' => 'You cannot transfer inventory to yourself.',
            ]);
        }

        $recipient = User::query()->findOrFail($recipientId);

        $inventoryIds = array_map(
            static fn (array $line): int => (int) $line['inventory_id'],
            $items,
        );

        if (count($inventoryIds) !== count(array_unique($inventoryIds))) {
            throw ValidationException::withMessages([
                'items' => 'An inventory row can only appear once in a transfer request.',
            ]);
        }

        return DB::transaction(function () use ($sender, $recipient, $items, $remarks): BondedWarehouse {
            $transferRequest = BondedWarehouse::query()->create([
                'transfer_date' => now()->toDateString(),
                'transferred_by' => $sender->getKey(),
                'transferred_to' => $recipient->getKey(),
                'remarks' => $remarks,
                'status' => 'pending',
            ]);

            $processedItemIds = [];

            foreach ($items as $line) {
                $selectedInventory = Inventory::query()
                    ->whereKey($line['inventory_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $stock = Stock::query()
                    ->whereKey($selectedInventory->stock_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $stock->user_id !== (int) $sender->getKey()) {
                    throw new AuthorizationException(
                        'You do not own inventory '.$selectedInventory->getKey().'.'
                    );
                }

                $itemId = (int) $selectedInventory->item_id;

                if (in_array($itemId, $processedItemIds, true)) {
                    throw ValidationException::withMessages([
                        'items' => "Item {$itemId} can only appear once in a transfer request.",
                    ]);
                }

                $processedItemIds[] = $itemId;

                $inventoryRows = Inventory::query()
                    ->where('item_id', $itemId)
                    ->whereHas('stock', fn ($query) =>
                    $query->where('user_id', $sender->getKey())
                    )
                    ->withSum(
                        [
                            'moveInventories as moved_quantity' => fn ($query) =>
                            $query->whereNull('move_inventories.deleted_at'),
                        ],
                        'quantity'
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $requested = $this->toMilli((string) $line['quantity']);

                if ($requested <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Transfer quantities must be greater than zero.',
                    ]);
                }

                $availableByInventory = [];
                $totalAvailable = 0;

                foreach ($inventoryRows as $row) {
                    if ($row->quantity_on_hand === null) {
                        throw ValidationException::withMessages([
                            'items' => "Inventory {$row->getKey()} has no initialized on-hand balance.",
                        ]);
                    }

                    $onHand = $this->toMilli((string) $row->quantity_on_hand);
                    $moved = $this->toMilli((string) ($row->moved_quantity ?? '0'));
                    $remaining = max(0, $onHand - $moved);

                    $availableByInventory[$row->getKey()] = $remaining;
                    $totalAvailable += $remaining;
                }

                if ($requested > $totalAvailable) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient available quantity for item {$itemId}.",
                    ]);
                }

                $remainingToTransfer = $requested;

                foreach ($inventoryRows as $row) {
                    if ($remainingToTransfer <= 0) {
                        break;
                    }

                    $rowAvailable = $availableByInventory[$row->getKey()];
                    $deduct = min($rowAvailable, $remainingToTransfer);

                    if ($deduct <= 0) {
                        continue;
                    }

                    $onHand = $this->toMilli((string) $row->quantity_on_hand);
                    $row->quantity_on_hand = $this->fromMilli($onHand - $deduct);
                    $row->save();

                    $transferRequest->items()->create([
                        'inventory_id' => $row->getKey(),
                        'quantity' => $this->fromMilli($deduct),
                    ]);

                    $remainingToTransfer -= $deduct;
                }
            }

            return $transferRequest->load('items.inventory');
        });
    }

    public function showById(int $id, User $user): BondedWarehouse
    {
        $request = BondedWarehouse::query()
            ->with([
                'sender:id,first_name,last_name',
                'recipient:id,first_name,last_name',
                'items.inventory:id,item_id,uom_id,account_title_id,quantity_on_hand',
                'items.inventory.item:id,code,name',
                'items.inventory.uom:id,name,symbol',
                'items.inventory.accountTitle:id,name',
                'transfer.items.bondedItem',
                'transfer.items.originInventory',
                'transfer.items.receivedInventory',
            ])
            ->whereKey($id)
            ->where(function ($query) use ($user) {
                $query->where('transferred_by', $user->getKey())
                    ->orWhere('transferred_to', $user->getKey());
            })
            ->firstOrFail();

        $groupedItems = $request->items
            ->groupBy(function (BondedItem $bondedItem): string {
                $inventory = $bondedItem->inventory;

                return json_encode([
                    $inventory?->item_id,
                    $inventory?->uom_id,
                    $inventory?->account_title_id,
                ], JSON_THROW_ON_ERROR);
            })
            ->map(function ($rows): BondedItem {
                $first = $rows->first();

                $totalMilli = $rows->sum(
                    fn (BondedItem $row): int => $this->toMilli(
                        (string) $row->getRawOriginal('quantity')
                    )
                );

                // Response-only aggregate; does not modify the stored row.
                $first->setAttribute('quantity', $this->fromMilli($totalMilli));
                $first->setAttribute(
                    'source_inventory_ids',
                    $rows->pluck('inventory_id')->unique()->values()->all()
                );

                return $first;
            })
            ->values();

        $request->setRelation('items', $groupedItems);

        return $request;
    }

    public function accept(
        BondedWarehouse $routeRequest,
        User $recipient
    ): Transfer {
        return DB::transaction(function () use ($routeRequest, $recipient): Transfer {
            $transferRequest = BondedWarehouse::query()
                ->whereKey($routeRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPendingRecipient($transferRequest, $recipient);

            $bondedItems = BondedItem::query()
                ->where('bonded_warehouse_id', $transferRequest->getKey())
                ->lockForUpdate()
                ->get();

            if ($bondedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'transfer' => 'This request contains no items.',
                ]);
            }

            $sender = User::query()->findOrFail($transferRequest->transferred_by);

            $senderName = trim("{$sender->first_name} {$sender->last_name}");
            $recipientName = trim("{$recipient->first_name} {$recipient->last_name}");

            $receivedStock = Stock::query()->create([
                'user_id' => $recipient->getKey(),
                'received_date' => now()->toDateString(),
                'supplier_name' => 'Internal transfer',
                'remarks' => "Transferred from {$senderName}"
                    ." to {$recipientName}"
            ]);

            $transfer = Transfer::query()->create([
                'bonded_warehouse_id' => $transferRequest->getKey(),
                'accepted_by' => $recipient->getKey(),
                'accepted_at' => now(),
            ]);

            foreach ($bondedItems as $bondedItem) {
                $origin = Inventory::query()
                    ->whereKey($bondedItem->inventory_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $receivedInventory = Inventory::query()->create([
                    'stock_id' => $receivedStock->getKey(),
                    'item_id' => $origin->item_id,
                    'uom_id' => $origin->uom_id,
                    'account_title_id' => $origin->account_title_id,
                    'actual_received' => $bondedItem->quantity,
                    'quantity_on_hand' => $bondedItem->quantity,
                ]);

                TransferItem::query()->create([
                    'transfer_id' => $transfer->getKey(),
                    'bonded_item_id' => $bondedItem->getKey(),
                    'origin_inventory_id' => $origin->getKey(),
                    'received_inventory_id' => $receivedInventory->getKey(),
                    'quantity' => $bondedItem->quantity,
                ]);
            }

            $transferRequest->forceFill([
                'status' => 'accepted',
                'release_date' => now()->toDateString(),
                'decided_at' => now(),
                'decided_by' => $recipient->getKey(),
            ])->save();

            return $transfer->load('items');
        });
    }

    public function reject(
        BondedWarehouse $routeRequest,
        User $recipient,
        ?string $reason = null,
    ): BondedWarehouse {
        return DB::transaction(function () use ($routeRequest, $recipient, $reason): BondedWarehouse {
            $transferRequest = BondedWarehouse::query()
                ->whereKey($routeRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPendingRecipient($transferRequest, $recipient);

            $bondedItems = BondedItem::query()
                ->where('bonded_warehouse_id', $transferRequest->getKey())
                ->lockForUpdate()
                ->get();

            if ($bondedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'transfer' => 'This request contains no items.',
                ]);
            }

            foreach ($bondedItems as $bondedItem) {
                $origin = Inventory::query()
                    ->whereKey($bondedItem->inventory_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($origin->quantity_on_hand === null) {
                    throw ValidationException::withMessages([
                        'transfer' => "Inventory {$origin->getKey()} has no initialized on-hand balance.",
                    ]);
                }

                $restored = $this->toMilli((string) $origin->quantity_on_hand)
                    + $this->toMilli((string) $bondedItem->quantity);

                $origin->quantity_on_hand = $this->fromMilli($restored);
                $origin->save();
            }

            $transferRequest->forceFill([
                'status' => 'rejected',
                'decided_at' => now(),
                'decided_by' => $recipient->getKey(),
                'rejection_reason' => $reason,
            ])->save();

            return $transferRequest->load('items');
        });
    }

    private function assertPendingRecipient(BondedWarehouse $request, User $recipient): void
    {
        if ((int) $request->transferred_to !== (int) $recipient->getKey()) {
            throw new AuthorizationException('Only the intended recipient can decide this transfer.');
        }

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'transfer' => 'This request has already been decided.',
            ]);
        }
    }

    private function toMilli(string $quantity): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,3})?$/', $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a decimal with up to three decimal places.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function fromMilli(int $quantity): string
    {
        return intdiv($quantity, 1000).'.'.str_pad(
                (string) ($quantity % 1000),
                3,
                '0',
                STR_PAD_LEFT,
            );
    }
}
