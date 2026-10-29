<?php

namespace App\Http\Controllers;

use App\Http\Requests\MiscellaneousRequest;
use App\Http\Resources\MiscellaneousResource;
use App\Http\Resources\StockResource;
use App\Services\MiscellaneousService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MiscellaneousController extends Controller
{
    public function __construct(private readonly MiscellaneousService $miscellaneous) {}

    public function index() {
        $miscellaneous = $this->miscellaneous->getMiscellaneous();

        if ($miscellaneous->isEmpty()) {
            return $this->responseNotFound(message: 'No stocks found.');
        }

        return $miscellaneous instanceof LengthAwarePaginator
            ? $miscellaneous->through(fn($item) => new MiscellaneousResource($item))
            : $this->responseSuccess('Miscellaneous issue retrieved successfully.', MiscellaneousResource::collection($miscellaneous));
    }

    public function store(MiscellaneousRequest $request)
    {
        $data = $request->validated();
        $miscellaneous = $this->miscellaneous->storeMiscellaneous($data);

        return response()->json([
            'message' => 'Miscellaneous issue created successfully.',
            'data' => $miscellaneous,
        ], 201);
    }
}
