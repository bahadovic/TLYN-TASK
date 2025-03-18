<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStoreRequest;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\OrderService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected TransactionService $transactionService;

    public function __construct($orderService ,$transactionService)
    {
        $this->orderService = $orderService;
        $this->transactionService = $transactionService;
    }


    public function store(OrderStoreRequest $request): JsonResponse
    {
        $result = $this->orderService->createOrder(params: $request->safe()->toArray());

        return response()->json($result['data'], $result['status']);
    }

    public function cancel($id): JsonResponse
    {
        $result = $this->orderService->cancelOrder(orderId: $id,userId: auth()->id());

        return response()->json($result['data'], $result['status']);
    }

    public function history(): JsonResponse
    {
        $result = $this->transactionService->getUserTransactionHistory(auth()->id());

        return response()->json($result['data'], $result['status']);
    }

}
