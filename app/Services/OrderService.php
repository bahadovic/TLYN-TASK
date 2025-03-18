<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;


readonly class OrderService
{
    public function createOrder($params): array
    {
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => auth()->id(),
                'type' => $params['type'],
                'amount' => $params['amount'],
                'price' => $params['price'],
            ]);

            $this->matchOrders($order);

            DB::commit();

            return [
                'data' => [
                    'order' => $order,
                ],
                'status' => 201,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return[
                'data' => [
                    'message' => 'An error occurred while processing your order',
                    'error' => $e->getMessage(),
                ],
                'status' => 500,
            ];
        }
    }

    public function cancelOrder($orderId, $userId): array
    {
        DB::beginTransaction();

        try {
            $order = Order::findOrFail($orderId);

            if ($order->user_id !== $userId) {
                throw new \Exception('Unauthorized');
            }

            $order->update(['status' => 'cancelled']);

            DB::commit();

            return [
                'data' => [
                    'order' => $order,
                ],
                'status' => 200,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return[
                'data' => [
                    'message' => 'Order cancellation failed:',
                    'error' => $e->getMessage(),
                ],
                'status' => 500,
            ];
        }
    }

    private function matchOrders(Order $order): void
    {
        $oppositeType = $order->type === 'buy' ? 'sell' : 'buy';

        $matchingOrders = Order::where('type', $oppositeType)
            ->where('status', 'open')
            ->where('price', $order->price)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($matchingOrders as $matchingOrder) {
            $amount = min($order->amount, $matchingOrder->amount);

            $fee = $this->calculateFee($amount);

            Transaction::create([
                'buy_order_id' => $order->type === 'buy' ? $order->id : $matchingOrder->id,
                'sell_order_id' => $order->type === 'sell' ? $order->id : $matchingOrder->id,
                'amount' => $amount,
                'price' => $order->price,
                'fee' => $fee,
            ]);

            $order->amount -= $amount;
            $matchingOrder->amount -= $amount;

            if ($order->amount <= 0) {
                $order->status = 'closed';
            }

            if ($matchingOrder->amount <= 0) {
                $matchingOrder->status = 'closed';
            }

            $order->save();
            $matchingOrder->save();

            if ($order->status === 'closed') {
                break;
            }
        }
    }

    private function calculateFee($amount): mixed
    {
        if ($amount <= 1) {
            $fee = $amount * 0.02;
        } elseif ($amount <= 10) {
            $fee = $amount * 0.015;
        } else {
            $fee = $amount * 0.01;
        }

        $fee = max($fee, 50000);
        return min($fee, 5000000);
    }

}
