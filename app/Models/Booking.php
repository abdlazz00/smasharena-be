<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'court_id',
        'booking_code',
        'customer_name',
        'customer_phone',
        'booking_date',
        'start_time',
        'end_time',
        'total_price',
        'status',
    ];

    // Relasi ke User (Customer)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Lapangan
    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    // Relasi ke Transaksi (One to One)
    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
    public function posOrders()
    {
        return $this->hasMany(Order::class);
    }
}
