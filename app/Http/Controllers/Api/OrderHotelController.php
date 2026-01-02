<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RezervareHotelService;
use App\Services\OrderHotelService;
use Illuminate\Http\Request;

class OrderHotelController extends Controller
{

    public function save(Request $request, RezervareHotelService $rezervarehotel, OrderHotelService $orderService)
    {
     
        $orderInfo = $request->all();
        $result = $orderService->saveOrder($orderInfo, $rezervarehotel);

        return response()->json($result, 200);
    }
   
}
