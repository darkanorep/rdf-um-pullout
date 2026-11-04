<?php

namespace App\Http\Controllers;

use App\Models\BondedWarehouse;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,accepted,rejected'],
            'direction' => ['nullable', 'in:all,incoming,outgoing'],
        ]);

        $results = $this->transfers->listForUser(
            $request->user(),
            $filters['status'] ?? null,
            $filters['direction'] ?? 'all',
        );

        return response()->json($results);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transferred_to' => ['required', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_id' => ['required', 'integer', 'distinct', 'exists:inventories,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);

        $transferRequest = $this->transfers->requestTransfer(
            $request->user(),
            (int) $data['transferred_to'],
            $data['items'],
            $data['remarks'] ?? null,
        );

        return response()->json(['data' => $transferRequest], 201);
    }

    public function show(Request $request, int $id)
    {
        $transfer = $this->transfers->showById($id, $request->user());

        return $this->responseSuccess(
            'Transfer retrieved successfully.',
            $transfer
        );
    }

    public function accept(Request $request, BondedWarehouse $bondedWarehouse): JsonResponse
    {
        $transfer = $this->transfers->accept($bondedWarehouse, $request->user());

        return response()->json(['data' => $transfer], 200);
    }

    public function reject(Request $request, BondedWarehouse $bondedWarehouse): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->transfers->reject($bondedWarehouse, $request->user(), $data['reason'] ?? null);

        return response()->json(['data' => $result], 200);
    }
}
