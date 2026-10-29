<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    private const ITEM_COUNT = 10_000;
    private const CHUNK_SIZE = 500;

    public function run(): void
    {
        $timestamp = now();

        $items = Item::factory()
            ->count(self::ITEM_COUNT)
            ->make();

        $rows = $items
            ->map(static function (Item $item) use ($timestamp): array {
                return array_merge($item->getAttributes(), [
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            })
            ->all();

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table('items')->insert($chunk);
        }
    }
}
