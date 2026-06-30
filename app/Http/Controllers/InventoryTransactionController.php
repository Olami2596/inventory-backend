<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\InventoryTransaction;

class InventoryTransactionController extends Controller
{
    public function index()
    {
        return InventoryTransaction::with(['product', 'creator'])->get();
    }

    public function store(StoreTransactionRequest $request)
    {
        $validated = $request->validated();
        $transaction = InventoryTransaction::create($validated);

        return response()->json($transaction, 201);
    }

    public function show(InventoryTransaction $transaction)
    {
        $transaction->load(['product', 'creator']);

        return response()->json($transaction, 200);
    }
}
