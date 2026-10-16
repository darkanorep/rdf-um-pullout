<?php

namespace App\Services;

use App\Models\AccountTitle;
use Illuminate\Support\Facades\Http;

class AccountTitleService
{
    /**
     * Create a new class instance.
     */
    public function __construct(private readonly AccountTitle $accountTitle) {}

    public function getAllAccountTitles()
    {
        return $this->accountTitle->query()->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function syncAccountTitles(): void
    {
        $url = Http::withHeaders(['API_KEY' => config('app.one_rdf_key')])
            ->get(config('app.one_rdf_url') . 'account_title_external', ['pagination' => 'none']);

        $data = json_decode($url->body(), true);

        collect(data_get($data, 'data'))->chunk(100)->each(function ($chunk) {
            foreach ($chunk as $item) {
                $accountTitleData = [
                    'sync_id' => $item['id'],
                    'code' => data_get($item, 'code'),
                    'name' => data_get($item, 'name'),
                    'account_type_name' => data_get($item, 'account_type_name'),
                    'account_group_name' => data_get($item, 'account_group_name'),
                    'account_sub_group_name' => data_get($item, 'account_sub_group_name'),
                    'financial_statement_name' => data_get($item, 'financial_statement_name'),
                    'normal_balance_name' => data_get($item, 'normal_balance_name'),
                    'allocation_name' => data_get($item, 'allocation_name'),
                    'account_unit_name' => data_get($item, 'account_unit_name'),
                    'charge' => data_get($item, 'charge'),
                    'created_at' => data_get($item, 'created_at'),
                    'updated_at' => data_get($item, 'updated_at'),
                    'deleted_at' => data_get($item, 'deleted_at'),
                ];

                //Use updateOrCreate instead of upsert
                $this->accountTitle->updateOrCreate(
                    [
                        'code' => data_get($item, 'code'),
                        'sync_id' => data_get($item, 'id'),
                    ], // Search criteria
                    $accountTitleData // Data to update/create
                );
            }
        });
    }
}
