<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TABEL MASTER PRODUK
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('other');
            $table->integer('stock')->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. TABEL HEADER PEMBELIAN (KULAKAN)
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name')->nullable();
            $table->date('purchase_date');
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
        });

        // 3. TABEL DETAIL ITEM PEMBELIAN
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('buy_price', 15, 2);
            $table->timestamps();
        });

        // 4. TABEL SHIFT KASIR (CLOSING SYSTEM)
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users'); // Siapa kasirnya
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('starting_cash', 15, 2); // Modal Awal
            $table->decimal('total_cash_sales', 15, 2)->default(0);
            $table->decimal('total_non_cash_sales', 15, 2)->default(0);
            $table->decimal('ending_cash_actual', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // 5. TABEL TRANSAKSI POS (ORDERS)
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions');
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_method')->default('cash');
            $table->string('payment_status')->default('paid');

            $table->timestamps();
        });

        // 6. TABEL DETAIL ITEM PENJUALAN
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('cost_at_sale', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('products');
    }
};
