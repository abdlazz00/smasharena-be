<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Import Model
use App\Models\Booking;
use App\Models\Order;       // Model Transaksi POS
use App\Models\OrderItem;   // Detail Item POS
use App\Models\Transaction; // Model Transaksi Booking
use App\Models\Product;
use App\Models\CashSession;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // ==========================================
        // 1. SEKTOR FINANSIAL (HARI INI)
        // ==========================================

        // A. Pendapatan Booking (Hari ini, status paid/completed)
        $bookingRevenue = Booking::whereDate('booking_date', $today)
            ->whereIn('status', ['paid', 'completed'])
            ->sum('total_price');

        // B. Pendapatan POS/Kantin (Hari ini, status paid)
        $posRevenue = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // C. Total Omzet
        $totalRevenue = $bookingRevenue + $posRevenue;

        // D. Metode Pembayaran (Cash vs Transfer/QRIS) - Gabungan Booking & POS
        // Cash dari Booking
        $bookingCash = Transaction::whereDate('paid_at', $today)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Cash dari POS
        $posCash = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('total_amount');

        // Non-Cash (Transfer/QRIS)
        $bookingTransfer = Transaction::whereDate('paid_at', $today)
            ->where('payment_method', '!=', 'cash')
            ->sum('amount');

        $posTransfer = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->where('payment_method', '!=', 'cash')
            ->sum('total_amount');

        // E. Status Shift Kasir (User yang login)
        $activeSession = CashSession::where('user_id', auth()->id())
            ->where('status', 'open')
            ->first();

        // ==========================================
        // 2. SEKTOR OPERASIONAL (ACTION CENTER)
        // ==========================================

        // A. Booking Aktif (Sedang Main Sekarang)
        $now = Carbon::now()->format('H:i:s');
        $activeBookings = Booking::whereDate('booking_date', $today)
            ->whereIn('status', ['paid', 'completed']) // Asumsi yg main sudah bayar/checkin
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->count();

        // B. Menunggu Pembayaran
        $pendingPayments = Booking::where('status', 'booked')->count();

        // C. Stok Menipis (Low Stock < 5)
        $lowStockProducts = Product::where('stock', '<=', 5)
            ->orderBy('stock', 'asc')
            ->take(5)
            ->get();

        // ==========================================
        // 3. SEKTOR ANALITIK (TREND)
        // ==========================================

        // A. Grafik Pendapatan 7 Hari Terakhir
        $chartLabels = [];
        $chartBookingData = [];
        $chartPosData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $chartLabels[] = $date->format('d M');

            // Sum Booking per hari
            $chartBookingData[] = Booking::whereDate('booking_date', $date)
                ->whereIn('status', ['paid', 'completed'])
                ->sum('total_price');

            // Sum POS per hari
            $chartPosData[] = Order::whereDate('created_at', $date)
                ->where('payment_status', 'paid')
                ->sum('total_amount');
        }

        // B. Grafik Jam Tersibuk (Heatmap) - Data 30 Hari Terakhir
        // Mengelompokkan booking berdasarkan JAM mulai
        $busyHours = Booking::select(DB::raw('HOUR(start_time) as hour'), DB::raw('count(*) as count'))
            ->where('booking_date', '>=', Carbon::today()->subDays(30))
            ->where('status', '!=', 'cancelled')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Format data jam 08 - 22 (isi 0 jika tidak ada booking)
        $hourlyData = [];
        for ($h = 8; $h <= 22; $h++) {
            $hourlyData[] = $busyHours[$h] ?? 0;
        }

        // ==========================================
        // 4. DATA DETAIL
        // ==========================================

        // A. Produk Terlaris (Bulan Ini)
        $topProducts = OrderItem::select('product_id', DB::raw('sum(quantity) as total_qty'))
            ->whereHas('order', function($q) {
                $q->where('payment_status', 'paid')
                    ->whereMonth('created_at', Carbon::now()->month);
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        // B. Transaksi Terbaru (Gabungan manual sederhana untuk display)
        // Ambil 5 booking terakhir
        $recentBookings = Booking::with('court')->latest()->take(5)->get();

        return response()->json([
            'financial' => [
                'total_revenue' => $totalRevenue,
                'booking_revenue' => $bookingRevenue,
                'pos_revenue' => $posRevenue,
                'cash_total' => $bookingCash + $posCash,
                'transfer_total' => $bookingTransfer + $posTransfer,
            ],
            'shift' => [
                'status' => $activeSession ? 'open' : 'closed',
                'user' => auth()->user()->name,
                'current_cash' => $activeSession ? ($activeSession->starting_cash + $activeSession->total_cash_sales) : 0
            ],
            'operational' => [
                'active_bookings' => $activeBookings,
                'pending_payments' => $pendingPayments,
                'low_stock' => $lowStockProducts
            ],
            'charts' => [
                'labels' => $chartLabels,
                'booking_series' => $chartBookingData,
                'pos_series' => $chartPosData,
                'busy_hours' => $hourlyData // Array data jam 8-22
            ],
            'lists' => [
                'top_products' => $topProducts,
                'recent_bookings' => $recentBookings
            ]
        ]);
    }
}
