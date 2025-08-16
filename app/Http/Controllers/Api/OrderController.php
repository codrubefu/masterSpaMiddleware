<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rezervarehotel;
use App\Services\RezervareHotelService;
use Illuminate\Http\Request;

class OrderController extends Controller
{

    public function save(Request $request, RezervareHotelService $rezervarehotel)
    {
        $orderInfo = $request->all();
        $orderBookingInfo = $orderInfo['custom_info'];
        $bookedRooms = [];
        foreach ($orderInfo['items'] as $item) {
            $x = 0;
            while ($x < $item['quantity']) {
                $rezervare = new Rezervarehotel();
                $roomsIds = array_map(fn($id) => (int)trim($id), explode(',', $item['product_meta_input']['_hotel_room_number'][0]));
                $hotelId = $item['product_meta_input']['_hotel_id'][0];
                $tipCamera = $item['product_meta_input']['_hotel_room_type_long'][0];
                // Calculate number of nights from start_date and end_date
                $start = new \DateTime($orderBookingInfo['start_date']);
                $end = new \DateTime($orderBookingInfo['end_date']);
                $pret = $item['subtotal']/$item['quantity'];
                $numberOfNights = $start->diff($end)->days;
                // Populate $freeRoomsIds with values in $roomsIds but not in $bookedRooms
                $freeRoomsIds = array_values(array_diff($roomsIds, $bookedRooms));
                
                $roomNumber = $rezervarehotel->getRoomNumber(
                    $freeRoomsIds,
                    $orderBookingInfo['start_date'],
                    $orderBookingInfo['end_date'],
                    $hotelId
                );

                $bookedRooms[] = $roomNumber[0];
                $rezervare->idcl = '1'; //igclient
                $rezervare->idclagentie1 = 0;
                $rezervare->idclagentie2 = 0;
                $rezervare->datas = $orderBookingInfo['start_date'];
                $rezervare->dataf = $orderBookingInfo['end_date'];
                $rezervare->camera = $roomNumber[0];
                $rezervare->tipcamera = $tipCamera;
                $rezervare->nrnopti = $numberOfNights;
                $rezervare->nradulti = 2;
                $rezervare->nrcopii = 0;
                $rezervare->tipmasa = 'Fara MD';
                $rezervare->prettipmasa = $pret;
                $rezervare->pachet  = $tipCamera;
                $rezervare->pretcamera = $pret;
                $rezervare->pretnoapte = $pret;
                $rezervare->total = $pret;
                $rezervare->idfirma = 1;
                $rezervare->utilizator = 'Web';
                $rezervare->save();
                $x++;
               
            }
        }
        // Process each item in the order
        // For example, you might want to validate or save each item
        return response()->json(true, 200);
    }
}
