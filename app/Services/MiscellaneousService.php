<?php

namespace App\Services;

use App\Models\Miscellaneous;
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
            ->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function storeMiscellaneous(array $data): Miscellaneous
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    $stock = $this->miscellaneous->create(Arr::except($data, 'miscellaneous_issue'));

                    $stock->miscellaneousIssue()->createMany($data['miscellaneous_issue']);

                    return $stock->load('miscellaneousIssue');
                });
            } catch (QueryException $exception) {
                if ( $attempt === 3) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create stock.');
    }
}
