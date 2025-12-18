<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    // Daftar kolom yang boleh diisi via Controller (Mass Assignment)
    protected $fillable = [
        'name',
        'slug',
        'sport_type',
        'court_type',
        'type',
        'description',
        'price_per_hour',
        'is_active',
        'image_path',
    ];

    // Mengubah tipe data otomatis saat diambil dari DB
    protected $casts = [
        'is_active' => 'boolean',
        'price_per_hour' => 'integer',
    ];

    protected $appends = ['image_url'];

    /**
     * Accessor untuk mendapatkan URL lengkap gambar.
     * Dipanggil di frontend dengan key: image_url
     */
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return url('storage/' . $this->image_path);
        }
        return null;
    }
}
