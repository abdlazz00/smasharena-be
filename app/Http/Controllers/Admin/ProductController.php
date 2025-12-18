<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // 1. LIST PRODUK (Bisa filter kategori)
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter Kategori
        if ($request->has('category') && $request->category != 'all') {
            $query->where('category', $request->category);
        }

        // Filter Status (Aktif/Nonaktif)
        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }

        // Search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('name', 'asc')->get();
        return response()->json($products);
    }

    // 2. TAMBAH PRODUK BARU
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:2048'
        ]);

        $data = $request->all();

        // Handle Upload Foto
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = url('storage/' . $path);
        }

        $product = Product::create($data);

        return response()->json(['message' => 'Produk berhasil ditambahkan', 'data' => $product]);
    }

    // 3. UPDATE PRODUK
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:2048'
        ]);

        $data = $request->all();

        // Ganti Foto (Hapus yang lama jika ada)
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = url('storage/' . $path);
        }

        $product->update($data);
        return response()->json(['message' => 'Produk diperbarui', 'data' => $product]);
    }

    // 4. HAPUS / NON-AKTIFKAN (Soft Delete Style)
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json(['message' => 'Status produk diubah', 'data' => $product]);
    }
}
