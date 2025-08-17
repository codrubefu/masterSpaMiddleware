<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RezervareHotelService
{
    protected function searchBookedRooms(string $checkInDate, string $checkOutDate, array $roomNrs, int $hotelId)
    {
        // Format dates to 'Y-m-d H:i:s.v' (e.g., 2023-12-07 14:00:00.000)
        $checkInDateFormatted = (new \DateTime($checkInDate))->format('Y-m-d H:i:s') . '.000';
        $checkOutDateFormatted = (new \DateTime($checkOutDate))->format('Y-m-d H:i:s') . '.000';
        return DB::table('rezervarehotel')
            ->whereIn('camera', $roomNrs)
            ->where(function ($query) use ($checkInDateFormatted, $checkOutDateFormatted) {
                $query->whereRaw('datas >= ? AND dataf <= ?', [$checkInDateFormatted, $checkOutDateFormatted]);
            })
            ->where('idhotel', $hotelId)
            ->get()->pluck('camera');
    }

    public function getRoomNumber(array $roomsIds, string $checkInDate, string $checkOutDate, int $hotelId)
    {

        $bookedRooms = $this->searchBookedRooms($checkInDate, $checkOutDate, $roomsIds, $hotelId);
       
        // Remove booked rooms from the list
        $availableRooms = array_diff($roomsIds, $bookedRooms->toArray());
        return !empty($availableRooms) ? $availableRooms : null;
    }

    public function getNextId(int $y): int
    {
        return DB::table('rezervarehotel')->max('idrezervarehotel') + $y;
    }

}

/*trznp trzdetnp trzdet trzfact trzdetfact nrfacspec - 1YY01numar*/