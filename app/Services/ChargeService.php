<?php

namespace App\Services;

use App\Models\Charge;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ChargeService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly Charge $charge) {}

    public function getAllCharges()
    {
        return $this->charge->query()->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function syncCharges(): void
    {
        $response = Http::withHeaders(['API_KEY' => config('app.one_rdf_key')])
            ->get(config('app.one_rdf_url') . 'charging_api', ['pagination' => 'none']);

        $uniqueBy = ['code', 'sync_id'];

        // Everything except the unique key columns gets updated on conflict.
        $updateColumns = [
            'name',
            'company_code', 'company_name',
            'business_unit_code', 'business_unit_name',
            'department_code', 'department_name',
            'unit_code', 'unit_name',
            'sub_unit_code', 'sub_unit_name',
            'location_code', 'location_name',
            'created_at', 'updated_at', 'deleted_at',
        ];

        collect($response->json('data', []))
            ->chunk(500)
            ->each(function ($chunk) use ($uniqueBy, $updateColumns) {
                $rows = $chunk->map(fn (array $item) => [
                    'sync_id'            => $item['id'],
                    'code'               => $item['code'],
                    'name'               => $item['name'],
                    'company_code'       => data_get($item, 'company_code'),
                    'company_name'       => data_get($item, 'company_name'),
                    'business_unit_code' => data_get($item, 'business_unit_code'),
                    'business_unit_name' => data_get($item, 'business_unit_name'),
                    'department_code'    => data_get($item, 'department_code'),
                    'department_name'    => data_get($item, 'department_name'),
                    'unit_code'          => data_get($item, 'unit_code'),
                    'unit_name'          => data_get($item, 'unit_name'),
                    'sub_unit_code'      => data_get($item, 'sub_unit_code'),
                    'sub_unit_name'      => data_get($item, 'sub_unit_name'),
                    'location_code'      => data_get($item, 'location_code'),
                    'location_name'      => data_get($item, 'location_name'),
                    'created_at'         => $this->toDateTime(data_get($item, 'created_at')),
                    'updated_at'         => $this->toDateTime(data_get($item, 'updated_at')),
                    'deleted_at'         => $this->toDateTime(data_get($item, 'deleted_at')),
                ])->values()->all();

                $this->charge->upsert($rows, $uniqueBy, $updateColumns);
            });
    }

    private function toDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }
}
