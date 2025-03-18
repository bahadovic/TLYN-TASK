<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'price',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function buyTransactions()
    {
        return $this->hasMany(Transaction::class, 'buy_order_id');
    }

    public function sellTransactions()
    {
        return $this->hasMany(Transaction::class, 'sell_order_id');
    }
}
