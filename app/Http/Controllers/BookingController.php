<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // 1. CEK KETERSEDIAAN SLOT (API UTAMA FRONTEND)
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'court_id' => 'required|exists:courts,id'
        ]);

        $date = $request->date;
        $courtId = $request->court_id;

        // Ambil semua booking di tanggal & lapangan tersebut yang statusnya bukan 'cancelled'
        $existingBookings = Booking::where('court_id', $courtId)
            ->where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        // Definisikan Slot Waktu (Per 2 Jam) dari jam 08:00 sampai 22:00
        $slots = [
            ['start' => '08:00:00', 'end' => '10:00:00'],
            ['start' => '10:00:00', 'end' => '12:00:00'],
            ['start' => '12:00:00', 'end' => '14:00:00'],
            ['start' => '14:00:00', 'end' => '16:00:00'],
            ['start' => '16:00:00', 'end' => '18:00:00'],
            ['start' => '18:00:00', 'end' => '20:00:00'],
            ['start' => '20:00:00', 'end' => '22:00:00'],
        ];

        $result = [];

        foreach ($slots as $slot) {
            // Default status Available
            $status = 'available';

            // Cek apakah slot ini bertabrakan dengan booking yang ada
            foreach ($existingBookings as $booking) {
                // Logika overlap:
                // Start slot < End booking  AND  End slot > Start booking
                if ($slot['start'] < $booking->end_time && $slot['end'] > $booking->start_time) {
                    $status = 'booked';
                    break;
                }
            }

            // Tambahkan ke hasil
            $result[] = [
                'start_time' => substr($slot['start'], 0, 5), // Ambil 08:00
                'end_time' => substr($slot['end'], 0, 5),     // Ambil 10:00
                'status' => $status
            ];
        }

        return response()->json($result);
    }

    // 2. BUAT BOOKING BARU
    public function store(Request $request)
    {
        $request->validate([
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i', // Format 08:00
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
        ]);

        // Hitung End Time (Otomatis +2 jam karena sistem kita fix 2 jam)
        $startTime = Carbon::createFromFormat('H:i', $request->start_time);
        $endTime = $startTime->copy()->addHours(2);

        // Format ke H:i:s untuk database
        $fmtStart = $startTime->format('H:i:s');
        $fmtEnd = $endTime->format('H:i:s');

        // --- PENTING: CEK LAGI APAKAH MASIH KOSONG (RACE CONDITION) ---
        // Takutnya ada orang lain booking di detik yang sama saat user mengisi form
        $isBooked = Booking::where('court_id', $request->court_id)
            ->where('booking_date', $request->date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($fmtStart, $fmtEnd) {
                $query->where('start_time', '<', $fmtEnd)
                    ->where('end_time', '>', $fmtStart);
            })
            ->exists();

        if ($isBooked) {
            return response()->json(['message' => 'Maaf, slot waktu ini baru saja diambil orang lain.'], 409); // 409 Conflict
        }

        $court = Court::find($request->court_id);
        $totalPrice = $court->price_per_hour * 2;

        $code = 'BK-' . date('Ym') . '-' . strtoupper(Str::random(5));
        $user = auth('sanctum')->user();

        $booking = Booking::create([
            'user_id' => $user ? $user->id : null,
            'court_id' => $request->court_id,
            'booking_code' => $code,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'booking_date' => $request->date,
            'start_time' => $fmtStart,
            'end_time' => $fmtEnd,
            'total_price' => $totalPrice,
            'status' => 'booked', // Default belum bayar
        ]);

        $message = "Halo {$request->customer_name}!\n\n" .
            "Booking Anda berhasil dibuat.\n" .
            "Kode: *{$code}*\n" .
            "Jadwal: {$request->date} | {$request->start_time}\n" .
            "Total: Rp " . number_format($totalPrice, 0, ',', '.') . "\n\n" .
            "Status: *BELUM BAYAR*\n" .
            "Silakan tunjukkan kode ini ke kasir/admin untuk pembayaran.";

        // Kirim WA (Fire and forget, jangan sampai error WA bikin booking gagal)
        FonnteService::send($request->customer_phone, $message, $booking->id);

        return response()->json([
            'message' => 'Booking berhasil dibuat!',
            'data' => $booking
        ], 201);
    }

    // 3. LIHAT DETAIL BOOKING (E-TICKET)
    public function show($code)
    {
        // Cari berdasarkan booking_code, sertakan data lapangan
        $booking = Booking::with('court')->where('booking_code', $code)->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking tidak ditemukan'], 404);
        }

        return response()->json($booking);
    }

    // 4. RIWAYAT BOOKING SAYA (KHUSUS CUSTOMER LOGIN)
    public function myBookings(Request $request)
    {
        $bookings = Booking::with('court')
            ->where('user_id', $request->user()->id)
            ->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json($bookings);
    }

    // ADMIN: LIHAT SEMUA BOOKING & POS Order (SEARCH & FILTER)
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'court', 'posOrders.orderItems.product', 'transaction']);

        // 1. Filter Status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // 2. Filter Tanggal Spesifik (Opsional)
        if ($request->has('date') && $request->date) {
            $query->where('booking_date', $request->date);
        }

        // 3. Fitur Search (Kode, Nama, atau No HP)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('booking_code', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_phone', 'LIKE', "%{$search}%");
            });
        }

        // 4. Urutkan dari yang terbaru (Booking Date & Created At)
        $bookings = $query->orderBy('booking_date', 'desc')
            ->orderBy('start_time', 'asc')
            ->paginate(10);

        return response()->json($bookings);
    }

    // 6. ACTION: LUNASI SEMUA (Booking + POS)
    public function settlePayment(Request $request, $id)
    {
        // Validasi Input
        $request->validate([
            'payment_method' => 'required|in:cash,transfer',
            // Gambar wajib jika transfer, opsional jika cash
            'proof_image' => 'required_if:payment_method,transfer|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $booking = Booking::findOrFail($id);

            // Hitung Total Tagihan (Lapangan + Kantin)
            $courtPrice = $booking->total_price;

            $unpaidOrders = Order::where('booking_id', $id)
                ->where('payment_status', 'unpaid')
                ->get();

            $posTotal = $unpaidOrders->sum('total_amount');
            $grandTotal = $courtPrice + $posTotal;

            // A. Upload Bukti (Jika Transfer)
            $proofPath = null;
            if ($request->hasFile('proof_image')) {
                $proofPath = $request->file('proof_image')->store('proofs', 'public');
            }

            // B. Simpan Record Transaksi
            Transaction::create([
                'booking_id' => $booking->id,
                'processed_by' => $request->user()->id, // Admin yg login
                'payment_method' => $request->payment_method,
                'amount' => $grandTotal,
                'proof_image' => $proofPath,
                'paid_at' => now(),
            ]);

            // C. Update Status Booking & POS
            $booking->update(['status' => 'paid']);

            foreach ($unpaidOrders as $order) {
                $order->update(['payment_status' => 'paid']);
            }

            DB::commit();
            return response()->json(['message' => 'Pembayaran lunas dan tersimpan!']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Gagal memproses: ' . $e->getMessage()], 500);
        }
    }

    // ADMIN: Tandai booking sudah selesai (orangnya sudah main)
    public function markAsCompleted($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        if ($booking->status !== 'paid') {
            return response()->json(['message' => 'Hanya booking yang sudah LUNAS yang bisa diselesaikan.'], 400);
        }

        $booking->status = 'completed';
        $booking->save();

        return response()->json(['message' => 'Status berhasil diubah menjadi Selesai.']);
    }
    // CUSTOMER: BATALKAN BOOKING
    public function cancel(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        // 1. Validasi Kepemilikan (Cegah orang lain cancel punya kita)
        if ($request->user()->id !== $booking->user_id) {
            return response()->json(['message' => 'Anda tidak berhak membatalkan booking ini.'], 403);
        }

        // 2. Validasi Status (Hanya boleh cancel jika BELUM BAYAR)
        if ($booking->status !== 'booked') {
            return response()->json(['message' => 'Booking yang sudah dibayar atau selesai tidak dapat dibatalkan.'], 400);
        }

        // 3. Ubah status jadi cancelled
        $booking->status = 'cancelled';
        $booking->save();

        return response()->json(['message' => 'Booking berhasil dibatalkan.']);
    }
}
