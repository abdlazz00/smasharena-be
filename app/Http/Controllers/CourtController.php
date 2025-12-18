<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CourtController extends Controller
{
    // PUBLIC: Lihat semua lapangan
    public function index(Request $request)
    {
        // Jika ada parameter ?all=1, tampilkan semua (untuk admin)
        if ($request->query('all')) {
            return response()->json(Court::all());
        }

        // Default customer: hanya yang aktif
        return response()->json(Court::where('is_active', true)->get());
    }

    // PUBLIC: Lihat detail 1 lapangan
    public function show($id)
    {
        $court = Court::find($id);
        if (!$court) return response()->json(['message' => 'Lapangan tidak ditemukan'], 404);

        return response()->json($court);
    }

    // ADMIN: Tambah Lapangan Baru
    public function store(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'name' => 'required|string|max:255',
            'sport_type' => 'required|string',
            'court_type' => 'required|in:indoor,outdoor',
            'type' => 'required|in:vinyl,parquet,cement',
            'price_per_hour' => 'required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        // 2. Upload Gambar (Jika ada)
        $imagePath = null;
        if ($request->hasFile('image')) {
            // Simpan di folder storage/app/public/courts
            $imagePath = $request->file('image')->store('courts', 'public');
        }

        // 3. Simpan ke Database
        $court = Court::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(5), // Slug unik
            'sport_type' => $request->sport_type,
            'court_type' => $request->court_type,
            'type' => $request->type,
            'description' => $request->description,
            'price_per_hour' => $request->price_per_hour,
            'is_active' => true,
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Lapangan berhasil ditambahkan',
            'data' => $court
        ], 201);
    }

    // ADMIN: Update Lapangan
    public function update(Request $request, $id)
    {
        $court = Court::find($id);
        if (!$court) return response()->json(['message' => 'Lapangan tidak ditemukan'], 404);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sport_type' => 'required|string',
            'court_type' => 'required|in:indoor,outdoor',
            'type' => 'sometimes|required|in:vinyl,parquet,cement',
            'price_per_hour' => 'sometimes|required|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Update Gambar jika ada yang baru
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($court->image_path && Storage::disk('public')->exists($court->image_path)) {
                Storage::disk('public')->delete($court->image_path);
            }
            $court->image_path = $request->file('image')->store('courts', 'public');
        }

        // Update field lain
        if ($request->has('name')) {
            $court->name = $request->name;
            $court->slug = Str::slug($request->name) . '-' . Str::random(5);
        }
        if ($request->has('sport_type')) $court->sport_type = $request->sport_type;
        if ($request->has('court_type')) $court->court_type = $request->court_type;
        if ($request->has('type')) $court->type = $request->type;
        if ($request->has('description')) $court->description = $request->description;
        if ($request->has('price_per_hour')) $court->price_per_hour = $request->price_per_hour;
        if ($request->has('is_active')) $court->is_active = $request->is_active;

        $court->save();

        return response()->json([
            'message' => 'Lapangan berhasil diupdate',
            'data' => $court
        ]);
    }

    // ADMIN: Hapus Lapangan
    public function destroy($id)
    {
        $court = Court::find($id);
        if (!$court) return response()->json(['message' => 'Lapangan tidak ditemukan'], 404);

        // Hapus file gambar fisiknya juga agar hemat storage
        if ($court->image_path && Storage::disk('public')->exists($court->image_path)) {
            Storage::disk('public')->delete($court->image_path);
        }

        $court->delete();

        return response()->json(['message' => 'Lapangan berhasil dihapus']);
    }
}
