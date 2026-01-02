<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RezervareHotelService;
use App\Services\OrderHotelService;
use App\Services\OrderSpaService;
use Illuminate\Http\Request;

class OrderSpaController extends Controller
{

    public function save(Request $request,  OrderSpaService $orderService)
    {
     
        $orderInfo = $request->all();
        $result = $orderService->saveOrder($orderInfo);
        return response()->json($result, 200);
    }
   
}
