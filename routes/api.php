<?php

use App\Http\Controllers\Admin\CashSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
// Public Routes
Route::post('/auth/register', [AuthController::class, 'register']); // BARU
Route::post('/auth/login', [AuthController::class, 'login']);       // BARU
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Protected Routes (Butuh Token Bearer)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/profile', [AuthController::class, 'updateProfile']);

});

Route::get('/courts', [CourtController::class, 'index']);
Route::get('/courts/{id}', [CourtController::class, 'show']);

// === ADMIN ROUTES (Harus Login & Role Admin) ===
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::post('/courts', [CourtController::class, 'store']);       // Tambah
    Route::post('/courts/{id}', [CourtController::class, 'update']); // Edit
    Route::delete('/courts/{id}', [CourtController::class, 'destroy']); // Hapus
    Route::post('/admin/bookings/{id}/complete', [BookingController::class, 'markAsCompleted']);
    Route::get('/admin/customers', [CustomerController::class, 'index']);

    // 1. MANAJEMEN PRODUK (MASTER BARANG)
    Route::get('/admin/products', [ProductController::class, 'index']);      // List Produk
    Route::post('/admin/products', [ProductController::class, 'store']);     // Tambah Produk
    Route::post('/admin/products/{id}', [ProductController::class, 'update']); // Edit Produk (Pakai POST utk file)
    Route::delete('/admin/products/{id}', [ProductController::class, 'destroy']); // Non-aktifkan

    // 2. MANAJEMEN STOK (PURCHASING)
    Route::get('/admin/purchases', [PurchaseController::class, 'index']);    // Riwayat Kulakan
    Route::post('/admin/purchases', [PurchaseController::class, 'store']);   // Input Stok Masuk (Restock)

    // 3. MANAJEMEN SHIFT KASIR (CLOSING)
    Route::get('/admin/cash-session/status', [CashSessionController::class, 'currentStatus']); // Cek Status Shift
    Route::get('/admin/cash-session/history', [CashSessionController::class, 'history']);      // Riwayat Shift
    Route::post('/admin/cash-session/open', [CashSessionController::class, 'open']);           // Buka Kasir
    Route::post('/admin/cash-session/close', [CashSessionController::class, 'close']);         // Tutup Kasir

    // 4. TRANSAKSI KASIR (POS)
    Route::get('/admin/orders', [OrderController::class, 'index']); // Riwayat Penjualan
    Route::post('/admin/orders', [OrderController::class, 'store']); // Checkout / Bayar
});

// === PUBLIC BOOKING ROUTES ===
// Cek slot kosong tidak butuh login
Route::get('/slots', [BookingController::class, 'checkAvailability']);
Route::get('/bookings/{code}', [BookingController::class, 'show']);

// === CUSTOMER/GUEST BOOKING ===
Route::post('/bookings', [BookingController::class, 'store']);

// === AUTHENTICATED USER ROUTES ===
Route::middleware('auth:sanctum')->group(function () {
    // Riwayat booking user login
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});

// === ADMIN ROUTES (Middleware: auth + is_admin) ===
Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    // 1. CRUD Lapangan (Sudah ada sebelumnya)
    Route::post('/courts', [CourtController::class, 'store']);
    Route::post('/courts/{id}', [CourtController::class, 'update']);
    Route::delete('/courts/{id}', [CourtController::class, 'destroy']);
    // 2. LIHAT SEMUA BOOKING (Untuk Admin Dashboard)
    Route::get('/admin/bookings', [BookingController::class, 'index']);
    Route::post('/admin/bookings/{id}/settle', [BookingController::class, 'settlePayment']); // (BARU: Pelunasan)
    // 3. PROSES PEMBAYARAN (Booking ID)
    Route::post('/admin/bookings/{id}/pay', [TransactionController::class, 'store']);
    // 4. DASHBOARD
    Route::get('/admin/dashboard-stats', [DashboardController::class, 'index']);
});
