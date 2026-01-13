# Smash Arena Backend (API)

![Laravel](https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)

**Smash Arena Backend** adalah layanan REST API yang dibangun menggunakan Laravel untuk mengelola operasional venue olahraga (Badminton/Futsal). Sistem ini mencakup manajemen booking lapangan, sistem Point of Sales (POS) untuk kantin/toko, serta notifikasi otomatis via WhatsApp.

## 🚀 Fitur Utama

### 1. Autentikasi & Pengguna
* **Role-based Access Control:** Membedakan akses antara `admin` dan `customer`.
* **Social Login:** Integrasi login menggunakan Google (via Laravel Socialite).
* **Sanctum Auth:** Keamanan token API untuk aplikasi mobile/frontend.

### 2. Manajemen Booking (Lapangan)
* **Cek Ketersediaan:** Endpoint publik untuk melihat slot waktu yang kosong.
* **Booking System:** Customer dapat melakukan booking lapangan secara mandiri.
* **Pembatalan:** Fitur pembatalan booking oleh customer.
* **Manajemen Admin:** Admin dapat melihat, mengubah, dan menyelesaikan status booking (Check-in/Selesai).

### 3. Point of Sales (POS) & Inventory
* **Manajemen Produk:** CRUD Master barang/produk yang dijual di venue.
* **Purchasing (Kulakan):** Manajemen stok masuk (restock) barang.
* **Shift Kasir:** Sistem Buka/Tutup kasir (`CashSession`) untuk memantau arus uang per shift.
* **Transaksi:** Pencatatan penjualan barang (F&B atau perlengkapan).

### 4. Notifikasi & Integrasi
* **WhatsApp Gateway:** Integrasi dengan **Fonnte** untuk mengirim notifikasi status booking secara otomatis.
* **Laporan:** Dashboard statistik untuk Admin.

## 🛠️ Persyaratan Sistem

* PHP ^8.2
* Composer
* MySQL Database

## 📦 Instalasi

Ikuti langkah-langkah berikut untuk menjalankan project di lokal:

1.  **Clone Repositori**
    ```bash
    git clone [https://github.com/username/smasharena-be.git](https://github.com/username/smasharena-be.git)
    cd smasharena-be
    ```

2.  **Install Dependencies**
    ```bash
    composer install
    ```

3.  **Konfigurasi Environment**
    Salin file `.env.example` menjadi `.env`:
    ```bash
    cp .env.example .env
    ```
    Sesuaikan konfigurasi database dan API key di file `.env`:
    ```ini
    DB_DATABASE=smash_arena_be
    DB_USERNAME=root
    DB_PASSWORD=

    # Konfigurasi Fonnte (WA Gateway)
    FONNTE_TOKEN=your_fonnte_token_here

    # Konfigurasi Google Login (Opsional)
    GOOGLE_CLIENT_ID=
    GOOGLE_CLIENT_SECRET=
    ```

4.  **Generate Application Key**
    ```bash
    php artisan key:generate
    ```

5.  **Migrasi Database & Seeding**
    Jalankan migrasi untuk membuat tabel dan data awal (termasuk akun admin default):
    ```bash
    php artisan migrate --seed
    ```

6.  **Jalankan Server**
    ```bash
    php artisan serve
    ```

## 🔑 Akun Default (Admin)

Setelah menjalankan `php artisan migrate --seed`, akun admin berikut akan dibuat secara otomatis:

| Role | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin@smash.id` | `123` |

## 📚 Dokumentasi API (Ringkasan)

Berikut adalah beberapa endpoint utama yang tersedia di `routes/api.php`:

| Method | Endpoint | Deskripsi | Auth |
| :--- | :--- | :--- | :--- |
| **POST** | `/api/auth/login` | Login user & admin | ❌ |
| **POST** | `/api/auth/register` | Registrasi customer baru | ❌ |
| **GET** | `/api/slots` | Cek slot lapangan tersedia | ❌ |
| **GET** | `/api/courts` | List semua lapangan | ❌ |
| **POST** | `/api/bookings` | Membuat booking baru | ❌ |
| **GET** | `/api/my-bookings` | History booking user login | ✅ |
| **POST** | `/api/admin/orders` | Transaksi POS (Kasir) | ✅ (Admin) |
| **POST** | `/api/admin/cash-session/open` | Buka shift kasir | ✅ (Admin) |

## 📝 Lisensi

Project ini dilisensikan di bawah [MIT license](https://opensource.org/licenses/MIT).
