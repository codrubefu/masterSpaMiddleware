<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentsController extends Controller
{
    private const TTL_SECONDS = 900;

    public function payment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'string', 'max:100'],
            'casa_id' => ['nullable', 'string', 'max:50'],
        ]);

        $paymentId = $validated['payment_id'];
        $casaId = $this->normalizeCasaId($validated['casa_id'] ?? null);

        $lock = Cache::lock($this->lockKey($paymentId), 5);

        try {
            $lock->block(2);
            $statusMap = Cache::get($this->paymentKey($paymentId), []);
            $statusMap[$casaId] = true;
            Cache::put($this->paymentKey($paymentId), $statusMap, now()->addSeconds(self::TTL_SECONDS));
        } finally {
            optional($lock)->release();
        }

        return response()->json([
            'payment_id' => $paymentId,
            'casa_id' => $casaId,
            'done' => true,
        ]);
    }

    public function isPaymentDone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => ['required', 'string', 'max:100'],
            'casa_id' => ['nullable', 'string', 'max:50'],
        ]);

        $paymentId = $validated['payment_id'];
        $statusMap = Cache::get($this->paymentKey($paymentId), []);
        $casaId = $this->normalizeCasaId($validated['casa_id'] ?? null);

        $done = $casaId === 'default'
            ? in_array(true, $statusMap, true)
            : (bool)($statusMap[$casaId] ?? false);

        return response()->json([
            'payment_id' => $paymentId,
            'casa_id' => $casaId,
            'done' => $done,
        ]);
    }

    private function paymentKey(string $paymentId): string
    {
        return 'payment_status:' . $paymentId;
    }

    private function lockKey(string $paymentId): string
    {
        return 'payment_status_lock:' . $paymentId;
    }

    private function normalizeCasaId(?string $casaId): string
    {
        return $casaId !== null && $casaId !== '' ? strtolower(trim($casaId)) : 'default';
    }
}
