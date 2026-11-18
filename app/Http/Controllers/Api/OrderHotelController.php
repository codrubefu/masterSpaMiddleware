<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\HotelOrderRequest;
use App\Services\OrderHotelService;
use App\Services\RezervareHotelService;
use Illuminate\Support\Facades\Log;

class OrderHotelController extends Controller
{
    public function save(HotelOrderRequest $request, RezervareHotelService $rezervarehotel, OrderHotelService $orderService)
    {
        try {
            // all(): the services need the full WooCommerce payload, not only validated keys.
            $result = $orderService->saveOrder($request->all(), $rezervarehotel);
        } catch (\Throwable $e) {
            Log::error('Hotel order failed', ['order_id' => $request->input('id'), 'message' => $e->getMessage(), 'exception' => $e]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json($result, 200);
    }
}
