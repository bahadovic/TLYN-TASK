<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;


readonly class OrderService
{
    public function createOrder($params): array
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($params['user_id']);

            if ($params['type'] === 'buy') {
                $totalCost = $params['amount'] * $params['price'];

                if ($user->balance < $totalCost) {
                    throw new \Exception('موجودی ریالی شما کافی نیست.');
                }

                $user->balance -= $totalCost;
            } elseif ($params['type'] === 'sell') {
                if ($user->gold_balance < $params['amount']) {
                    throw new \Exception('موجودی طلای شما کافی نیست.');
                }

                $user->gold_balance -= $params['amount'];
            }

            $user->save();

            $order = Order::create($params);

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
                'status' => 400,
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

            if ($order->status !== 'open') {
                throw new \Exception('سفارش قابل لغو نیست.');
            }

            $user = User::find($userId);

            if ($order->type === 'buy') {
                $user->balance += $order->amount * $order->price;
            } elseif ($order->type === 'sell') {
                $user->gold_balance += $order->amount;
            }

            $user->save();

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
                'status' => 400,
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

            $buyer = User::find($order->type === 'buy' ? $order->user_id : $matchingOrder->user_id);
            $seller = User::find($order->type === 'sell' ? $order->user_id : $matchingOrder->user_id);

            $buyer->gold_balance += $amount;
            $seller->balance += ($amount * $order->price) - $fee;

            $buyer->save();
            $seller->save();

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
