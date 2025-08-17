<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RezervareHotelService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function save(Request $request, RezervareHotelService $rezervarehotel, OrderService $orderService)
    {
      
        $orderInfo = $request->all();
        $result = $orderService->saveOrder($orderInfo, $rezervarehotel);
        $orderService->generateInvoice($orderInfo);

        return response()->json($result, 200);
    }
   
}
