<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Transaction;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    // ADMIN: PROSES PEMBAYARAN
    public function store(Request $request, $booking_id)
    {
        // 1. Validasi Input Admin
        $request->validate([
            'payment_method' => 'required|in:cash,transfer',
            'amount' => 'required|numeric|min:0',
            // Gambar wajib jika transfer, opsional jika cash
            'proof_image' => 'required_if:payment_method,transfer|image|max:2048',
        ]);

        $booking = Booking::find($booking_id);

        if (!$booking) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }

        if ($booking->status === 'paid' || $booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking ini sudah diproses atau dibatalkan.'], 400);
        }

        // Gunakan DB Transaction agar atomik (semua sukses atau semua gagal)
        try {
            DB::beginTransaction();

            // A. Upload Bukti (Jika ada)
            $proofPath = null;
            if ($request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('proofs', 'public');
            }

            // B. Simpan Data Transaksi
            $transaction = Transaction::create([
                'booking_id' => $booking->id,
                'processed_by' => $request->user()->id, // ID Admin yang login
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'proof_image' => $proofPath,
                'paid_at' => now(),
            ]);

            // C. Update Status Booking jadi 'paid'
            $booking->status = 'paid';
            $booking->save();

            DB::commit(); // Simpan perubahan permanen

            $msgLunas = "Terima Kasih, {$booking->customer_name}!\n\n" .
                "Pembayaran untuk Kode Booking *{$booking->booking_code}* telah kami terima.\n" .
                "Status: *LUNAS* ✅\n\n" .
                "Selamat bermain!";

            FonnteService::send($booking->customer_phone, $msgLunas, $booking->id);

            return response()->json([
                'message' => 'Pembayaran berhasil diproses.',
                'data' => $transaction
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika ada error
            return response()->json(['message' => 'Gagal memproses transaksi: ' . $e->getMessage()], 500);
        }
    }
}
