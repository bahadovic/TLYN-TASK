<?php

namespace App\Services;

use App\Models\Transaction;

class TransactionService
{
    public function getUserTransactionHistory($userId)
    {
        try {
            $transactions = Transaction::whereHas('buyOrder', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->orWhereHas('sellOrder', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->get();

            return [
                'data' => $transactions,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            return[
                'data' => [
                    'message' => 'Failed to fetch transaction history:',
                    'error' => $e->getMessage(),
                ],
                'status' => 500,
            ];

        }
    }
}
