<?php

namespace App\Http\Controllers;

use App\Http\Requests\MiscellaneousReceiptRequest;
use App\Http\Resources\MiscellaneousReceiptResource;
use App\Models\MiscellaneousReceipt;
use App\Services\MiscellaneousReceiptsService;
use Illuminate\Http\Request;

class MiscellaneousReceiptController extends Controller
{
    public function __construct(private readonly MiscellaneousReceiptsService $miscellaneousReceiptsService) {}

    public function index() {
        $miscellaneousReceipts = $this->miscellaneousReceiptsService->getMiscellaneousReceipts();

        if ($miscellaneousReceipts->isEmpty()) {
            return $this->responseNotFound(message: 'No miscellaneous receipts found.');
        }

        return $this->responseSuccess(
            message: 'Miscellaneous Receipts retrieved successfully.',
            data: MiscellaneousReceiptResource::collection($miscellaneousReceipts)
        );
    }
    public function store(MiscellaneousReceiptRequest $request) {
        $data = $request->validated();
        $miscellaneousReceipt = $this->miscellaneousReceiptsService->storeMiscellaneousReceipt($data);

        return $this->responseCreated(
            message: 'Miscellaneous Receipt created successfully.',
            data: new MiscellaneousReceiptResource($miscellaneousReceipt)
        );
    }

    public function show(int $id) {
        $miscellaneousReceipt = $this->miscellaneousReceiptsService->findById($id);

        if (! $miscellaneousReceipt) {
            return $this->responseNotFound(message: 'Miscellaneous Receipt not found.');
        }

        return $this->responseSuccess(
            message: 'Miscellaneous Receipt retrieved successfully.',
            data: new MiscellaneousReceiptResource($miscellaneousReceipt)
        );
    }

    public function update(MiscellaneousReceiptRequest $request, MiscellaneousReceipt $miscellaneousReceipt) {
        $data = $request->validated();
        $updatedMiscellaneousReceipt = $this->miscellaneousReceiptsService->updateMiscellaneousReceipt($data, $miscellaneousReceipt);

        return $this->responseAccepted(
            message: 'Miscellaneous Receipt updated successfully.',
            data: new MiscellaneousReceiptResource($updatedMiscellaneousReceipt)
        );
    }

    public function destroy(int $id)
    {
        $this->miscellaneousReceiptsService->destroyMiscellaneousReceipt($id);

        return $this->responseAccepted(
            message: 'Miscellaneous Receipt status updated successfully.'
        );
    }
}
