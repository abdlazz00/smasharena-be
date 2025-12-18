<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\WhatsappLog;

class FonnteService
{
    /**
     * Kirim Pesan WA via Fonnte
     * @param string $target Nomor HP Tujuan
     * @param string $message Isi Pesan
     * @param int|null $bookingId ID Booking (Opsional, untuk log)
     */
    public static function send($target, $message, $bookingId = null)
    {
        $token = env('FONNTE_TOKEN');

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62', // Default Indonesia
            ]);

            $responseData = $response->json();

            // Cek status dari respon Fonnte (biasanya ada key 'status')
            $status = $response->successful() ? 'sent' : 'failed';

            // Simpan Log ke Database
            WhatsappLog::create([
                'booking_id' => $bookingId,
                'target_phone' => $target,
                'message' => $message,
                'status' => $status,
                'response_data' => $responseData
            ]);

            return $responseData;

        } catch (\Exception $e) {
            // Jika error koneksi/coding, catat log failed
            WhatsappLog::create([
                'booking_id' => $bookingId,
                'target_phone' => $target,
                'message' => $message,
                'status' => 'failed',
                'response_data' => ['error' => $e->getMessage()]
            ]);

            return false;
        }
    }
}
