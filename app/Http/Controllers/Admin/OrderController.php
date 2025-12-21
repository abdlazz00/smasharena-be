<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\CashSession;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // 1. LIST TRANSAKSI
    public function index()
    {
        $orders = Order::with(['user', 'booking', 'cashSession.user', 'orderItems.product'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        return response()->json($orders);
    }

    // 2. PROSES CHECKOUT (SIMPAN TRANSAKSI)
    public function store(Request $request)
    {
        // A. Validasi Input
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,qris,transfer,open_bill',
            'booking_id' => 'nullable|exists:bookings,id', // Wajib jika open_bill
        ]);

        // B. Cek Sesi Kasir (Wajib Open Shift dulu!)
        $session = CashSession::where('user_id', Auth::id())->where('status', 'open')->first();
        if (!$session) {
            return response()->json(['message' => 'Kasir belum dibuka! Silakan buka shift terlebih dahulu.'], 403);
        }

        // C. Validasi Open Bill (Harus ada booking valid)
        if ($request->payment_method === 'open_bill' && !$request->booking_id) {
            return response()->json(['message' => 'Pembayaran Open Bill wajib memilih Kode Booking.'], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Hitung Total & Generate Invoice
            $totalAmount = 0;
            // Kita generate kode invoice unik
            $invoiceCode = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Status bayar: Kalau Open Bill = Unpaid, Selain itu = Paid
            $paymentStatus = ($request->payment_method === 'open_bill') ? 'unpaid' : 'paid';

            // 2. Buat Header Order
            $order = Order::create([
                'invoice_code' => $invoiceCode,
                'user_id' => null,
                'booking_id' => $request->booking_id,
                'cash_session_id' => $session->id,
                'total_amount' => 0, // Update nanti setelah loop
                'payment_method' => $request->payment_method,
                'payment_status' => $paymentStatus,
            ]);

            // 3. Loop Item, Kurangi Stok, Simpan Detail
            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                // Cek Stok Cukup?
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak cukup (Sisa: {$product->stock})");
                }

                // Kurangi Stok
                $product->stock -= $item['quantity'];
                $product->save();

                // Hitung Subtotal
                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                // Simpan Item Order
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,           // Harga Jual saat ini
                    'cost_at_sale' => $product->cost_price // KUNCI AKUNTANSI: Modal saat ini
                ]);
            }

            // Update Total Amount di Header
            $order->update(['total_amount' => $totalAmount]);

            // 4. Jika Open Bill, Tambahkan ke Tagihan Booking (Opsional Logic)
            // Di sini kita cuma link-kan saja. Nanti di halaman kasir booking (TransactionPage),
            // kita harus hitung: Total Lapangan + Total Order POS yg statusnya unpaid.

            DB::commit();

            return response()->json(['message' => 'Transaksi berhasil!', 'data' => $order]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
