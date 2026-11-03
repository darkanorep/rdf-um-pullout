<?php

namespace App\Http\Controllers;

use App\Http\Requests\MiscellaneousRequest;
use App\Http\Resources\MiscellaneousResource;
use App\Models\Miscellaneous;
use App\Services\MiscellaneousService;
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
            'data' => new MiscellaneousResource($miscellaneous),
        ], 201);
    }

    public function show(int $id) {
        $miscellaneous = $this->miscellaneous->findById($id);

        if (! $miscellaneous) {
            return $this->responseNotFound(message: 'Miscellaneous issue not found.');
        }

        return $this->responseSuccess(
            message: 'Miscellaneous issue retrieved successfully.',
            data: new MiscellaneousResource($miscellaneous)
        );
    }

    public function update(MiscellaneousRequest $request, Miscellaneous $miscellaneous) {
        $data = $request->validated();
        $updatedMiscellaneous = $this->miscellaneous->updateMiscellaneous($data, $miscellaneous);

        return $this->responseAccepted(
            message: 'Miscellaneous issue updated successfully.',
            data: new MiscellaneousResource($updatedMiscellaneous)
        );
    }

    public function destroy(int $id)
    {
        $this->miscellaneous->deleteMiscellaneousReceipt($id);

        return $this->responseAccepted(
            message: 'Miscellaneous issue status updated successfully.'
        );
    }
}
