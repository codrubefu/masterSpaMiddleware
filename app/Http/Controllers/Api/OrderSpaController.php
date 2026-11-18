<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\SpaOrderRequest;
use App\Services\OrderSpaService;
use Illuminate\Support\Facades\Log;

class OrderSpaController extends Controller
{
    public function save(SpaOrderRequest $request, OrderSpaService $orderService)
    {
        try {
            // all(): the services need the full WooCommerce payload, not only validated keys.
            $result = $orderService->saveOrder($request->all());
        } catch (\Throwable $e) {
            Log::error('Spa order failed', ['order_id' => $request->input('id'), 'message' => $e->getMessage(), 'exception' => $e]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json($result, 200);
    }
}
