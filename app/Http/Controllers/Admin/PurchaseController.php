<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    // 1. RIWAYAT KULAKAN
    public function index()
    {
        $purchases = Purchase::with(['purchaseItems.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json($purchases);
    }

    // 2. PROSES RESTOCK (BARANG MASUK)
    public function store(Request $request)
    {
        $request->validate([
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.buy_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // A. Buat Header Pembelian
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['buy_price'];
            }
            $purchase = Purchase::create([
                'supplier_name' => $request->supplier_name,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
            ]);
            // B. Loop Item & Update Stok Master
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'buy_price' => $item['buy_price'],
                ]);

                // 2. UPDATE MASTER PRODUK (Logika Last Purchase Price)
                $product = Product::lockForUpdate()->find($item['product_id']);
                // Tambah Stok
                $product->stock += $item['quantity'];
                // Timpa Harga Modal dengan Harga Beli Terbaru
                $product->cost_price = $item['buy_price'];

                $product->save();
            }

            DB::commit();

            return response()->json(['message' => 'Restock berhasil!', 'data' => $purchase]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Gagal restock: ' . $e->getMessage()], 500);
        }
    }
}
