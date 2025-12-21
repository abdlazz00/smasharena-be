<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashSessionController extends Controller
{
    // 1. CEK STATUS SHIFT (Apakah user ini sedang buka kasir?)
    public function currentStatus()
    {
        $session = CashSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->latest()
            ->first();

        if (!$session) {
            return response()->json(['status' => 'closed', 'message' => 'Anda belum membuka kasir.']);
        }

        // --- UPDATE: Hitung Omzet Cash Secara Real-time ---
        // Kita hitung jumlah order yang 'paid' dan metodenya 'cash' pada sesi ini
        $currentCashSales = $session->orders()
            ->where('payment_method', 'cash')
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Kembalikan format 'session' agar sesuai dengan Frontend
        return response()->json([
            'status' => 'open',
            'session' => [
                'id' => $session->id,
                'starting_cash' => $session->starting_cash,
                'total_cash_sales' => $currentCashSales
            ]
        ]);
    }

    // 2. BUKA KASIR (OPEN SHIFT)
    public function open(Request $request)
    {
        $existing = CashSession::where('user_id', Auth::id())->where('status', 'open')->first();
        if ($existing) {
            return response()->json(['message' => 'Anda sudah memiliki sesi aktif!'], 400);
        }

        $request->validate([
            'starting_cash' => 'required|numeric|min:0'
        ]);

        $session = CashSession::create([
            'user_id' => Auth::id(),
            'opened_at' => now(),
            'starting_cash' => $request->starting_cash,
            'status' => 'open'
        ]);

        return response()->json(['message' => 'Shift Kasir Dibuka. Selamat bekerja!', 'data' => $session]);
    }

    // 3. TUTUP KASIR (CLOSE SHIFT / CLOSING)
    public function close(Request $request)
    {
        $session = CashSession::where('user_id', Auth::id())->where('status', 'open')->first();

        if (!$session) {
            return response()->json(['message' => 'Tidak ada sesi aktif.'], 404);
        }

        $request->validate([
            'ending_cash_actual' => 'required|numeric|min:0',
            'note' => 'nullable|string'
        ]);

        // --- HITUNG REKAP SISTEM ---
        // Hitung total penjualan CASH pada sesi ini
        $cashSales = $session->orders()
            ->where('payment_method', 'cash')
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Hitung total penjualan NON-CASH (QRIS/Transfer) - cuma buat laporan
        $nonCashSales = $session->orders()
            ->whereIn('payment_method', ['qris', 'transfer'])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Uang yang SEHARUSNYA ada di sistem (Modal + Penjualan Tunai)
        $expectedCash = $session->starting_cash + $cashSales;

        // Selisih (Uang Fisik - Uang Sistem)
        // Kalau minus (-) berarti TEKOR/HILANG. Kalau plus (+) berarti LEBIH.
        $difference = $request->ending_cash_actual - $expectedCash;

        // --- SIMPAN DATA CLOSING ---
        $session->update([
            'closed_at' => now(),
            'total_cash_sales' => $cashSales,
            'total_non_cash_sales' => $nonCashSales,
            'ending_cash_actual' => $request->ending_cash_actual,
            'cash_difference' => $difference,
            'status' => 'closed',
            'note' => $request->note
        ]);

        return response()->json([
            'message' => 'Shift Berhasil Ditutup.',
            'summary' => [
                'modal_awal' => $session->starting_cash,
                'penjualan_tunai' => $cashSales,
                'seharusnya_ada' => $expectedCash,
                'fisik_uang' => $request->ending_cash_actual,
                'selisih' => $difference
            ]
        ]);
    }

    // 4. RIWAYAT SHIFT (Untuk Laporan Admin)
    public function history()
    {
        $sessions = CashSession::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($sessions);
    }
}
