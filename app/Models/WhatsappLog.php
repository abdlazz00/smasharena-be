<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'target_phone',
        'message',
        'status',
        'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
    ];
}
