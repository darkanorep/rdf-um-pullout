<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountTitleResource;
use App\Services\AccountTitleService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountTitleController extends Controller
{
    public function __construct(private readonly AccountTitleService $accountTitleService) {}

    public function sync() {
        try {
            $this->accountTitleService->syncAccountTitles();
            return $this->responseSuccess('Account titles synchronized successfully.');
        } catch (\Exception $e) {
            return $this->responseWithCustomError('Error', 'Failed to synchronize account titles: ' . $e->getMessage(), 500);
        }
    }

    public function index()
    {
        $accountTitles = $this->accountTitleService->getAllAccountTitles();

        if ($accountTitles->isEmpty()) {
            return $this->responseNotFound(message: 'No account titles found.');
        }

        return $accountTitles instanceof LengthAwarePaginator
            ? $accountTitles->through(fn ($item) => new AccountTitleResource($item))
            : $this->responseSuccess(
                'Account titles retrieved successfully.',
                AccountTitleResource::collection($accountTitles)
            );
    }
}
