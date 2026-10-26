<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        // 1. Konfigurasi Midtrans
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized', true);
        Config::$is3ds = config('midtrans.is_3ds', true);
    }

    /**
     * Method ini yang HILANG sebelumnya.
     * Fungsinya: Simpan ke DB -> Minta Token ke Midtrans -> Balikin Token
     */
    public function createTransaction(User $user, string $planSlug, int $amount): array
    {
        if (config('midtrans.mode') === 'demo') {
            return $this->createDemoTransaction($user, $planSlug, $amount);
        }

        if (!config('midtrans.server_key') || !config('midtrans.client_key')) {
            return [
                'success' => false,
                'message' => 'Konfigurasi Midtrans belum lengkap. Isi server key dan client key sandbox, atau gunakan PAYMENT_MODE=demo.',
            ];
        }

        // 1. Buat Order ID Unik (Pakai Helper dari Model Transaction kamu)
        // Format: PORT-{TIMESTAMP}-{USER_ID}
        $orderId = Transaction::generateOrderId($user->id);

        // 2. Siapkan Parameter Midtrans
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount, // Pastikan integer
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => $planSlug,
                    'price' => (int) $amount,
                    'quantity' => 1,
                    'name' => strtoupper(str_replace('_', ' ', $planSlug)),
                ]
            ]
        ];

        try {
            // 3. Request Snap Token ke Midtrans
            $snapToken = Snap::getSnapToken($params);

            // 4. Simpan ke Database (PENTING: Sebelum return token)
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'subscription_plan' => $planSlug,
                'amount' => $amount,
                'total_amount' => $amount,
                'status' => 'pending',
                'midtrans_response' => json_encode($params),
            ]);

            // 5. Kembalikan data sukses
            return [
                'success' => true,
                'snap_token' => $snapToken,
                'transaction' => $transaction
            ];

        } catch (\Exception $e) {
            Log::error('Midtrans Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal koneksi ke Midtrans: ' . $e->getMessage()
            ];
        }
    }

    private function createDemoTransaction(User $user, string $planSlug, int $amount): array
    {
        return DB::transaction(function () use ($user, $planSlug, $amount) {
            $durationMonths = $planSlug === 'pro_yearly' ? 12 : 1;

            $user->activatePro($durationMonths);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'order_id' => Transaction::generateOrderId($user->id),
                'payment_type' => 'demo',
                'payment_method' => 'practicum',
                'subscription_plan' => $planSlug,
                'amount' => $amount,
                'total_amount' => $amount,
                'status' => 'success',
                'paid_at' => now(),
                'subscription_start_date' => $user->subscription_started_at,
                'subscription_end_date' => $user->subscription_expired_at,
                'midtrans_response' => ['mode' => 'demo'],
                'notes' => 'Simulasi pembayaran untuk modul praktikum.',
            ]);

            return [
                'success' => true,
                'demo' => true,
                'snap_token' => null,
                'transaction' => $transaction,
            ];
        });
    }
}
