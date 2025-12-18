<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'processed_by', // ID Admin
        'payment_method',
        'amount',
        'proof_image',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'integer',
    ];

    // Agar Frontend dapat URL gambar langsung (bukan cuma path)
    protected $appends = ['proof_image_url'];

    /**
     * Accessor: Mendapatkan URL lengkap bukti transfer
     */
    public function getProofImageUrlAttribute()
    {
        if ($this->proof_image) {
            return url('storage/' . $this->proof_image);
        }
        return null;
    }

    // Relasi ke Booking
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Relasi ke Admin yang memproses
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
