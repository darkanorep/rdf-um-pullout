<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /** @var array<string, array<int, int>> */
    private static array $activeForeignKeyIds = [];

    public function definition(): array
    {
        $code = $this->faker->unique()->bothify('ITM-##########');
        $name = ucfirst($this->faker->words(3, true));

        return [
            'code' => $code,
            'name' => $name,
            'slug' => 'item-'.strtolower(str_replace(' ', '-', $code)),
            'uom_id' => $this->randomActiveId('unit_of_measurements'),
            'category_id' => $this->randomActiveId('categories'),
            'warehouse_id' => $this->randomActiveId('warehouses'),
        ];
    }

    private function randomActiveId(string $table): int
    {
        if (! isset(self::$activeForeignKeyIds[$table])) {
            self::$activeForeignKeyIds[$table] = DB::table($table)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        $ids = self::$activeForeignKeyIds[$table];

        if ($ids === []) {
            throw new RuntimeException(
                "No active records found in the {$table} table."
            );
        }

        return $this->faker->randomElement($ids);
    }
}
