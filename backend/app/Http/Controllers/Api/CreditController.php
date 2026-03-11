<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(private CreditService $creditService) {}

    public function getBalance(Request $request): JsonResponse
    {
        $balance = $this->creditService->getBalance($request->user()->id);

        return response()->json([
            'success' => true,
            'data'    => ['balance' => $balance],
        ]);
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $transactions = $this->creditService->getTransactions($request->user()->id);

        $data = $transactions->map(fn ($t) => [
            'id'             => $t->id,
            'type'           => $t->type,
            'amount'         => $t->amount,
            'balance_before' => $t->balance_before,
            'balance_after'  => $t->balance_after,
            'description'    => $t->description,
            'ride_id'        => $t->ride_id,
            'created_at'     => $t->created_at->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
