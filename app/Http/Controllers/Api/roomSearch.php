<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class roomSearch extends Controller
{
    protected $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function searchAvailableRoomCombinations(Request $request)
    {
        $adults = (int) $request->input('adults', 1);
        $kids = (int) $request->input('kids', 0);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $numberOfRooms = (int) $request->input('number_of_rooms', 1);

        // Step 1: Get all rooms
        $rooms = DB::table('camerehotel')->get();
        $roomList = $rooms->map(function($room) {
            return [
                'nr' => $room->nr,
                'adultMax' => $room->adultMax,
                'kidMax' => $room->kidMax
            ];
        })->toArray();
        // Format input dates to match DB format
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';

        // Step 2: Generate all combinations of rooms with the specified number
        $combinations = $this->getRoomCombinations($roomList, $adults, $kids, $numberOfRooms);
        // Step 3: For each combination, check if all rooms are available
        $availableCombinations = [];
        foreach ($combinations as $combo) {
            $roomNrs = array_column($combo, 'nr');
            $reserved = DB::table('rezervarehotel')
                ->whereIn('camera', $roomNrs)
                ->where(function($query) use ($startDateTime, $endDateTime) {
                    $query->whereRaw('? < dataf AND ? > datas', [$startDateTime, $endDateTime]);
                })
                ->exists();
            if (!$reserved) {
                $availableCombinations[] = $combo;
            }
        }

        return response()->json($availableCombinations);
    }

    // Helper to generate all combinations of rooms that meet the people requirement and number of rooms
    private function getRoomCombinations($rooms, $adults, $kids, $numberOfRooms)
    {
        $results = [];
        $n = count($rooms);
        $totalPeople = $adults + $kids;
        $r = min($numberOfRooms, $n);
        if ($r < 1) return $results;
        $indices = range(0, $r - 1);
        while (true) {
            $combo = [];
            $adultSum = 0;
            $kidSum = 0;
            foreach ($indices as $i) {
                $combo[] = $rooms[$i];
                $adultSum += $rooms[$i]['adultMax'];
                $kidSum += $rooms[$i]['kidMax'];
            }
            // Allow any room(s) where the total capacity fits, regardless of split
            if (($adultSum + $kidSum) >= $totalPeople) {
                $results[] = $combo;
            }
            // Next combination
            $i = $r - 1;
            while ($i >= 0 && $indices[$i] == $n - $r + $i) $i--;
            if ($i < 0) break;
            $indices[$i]++;
            for ($j = $i + 1; $j < $r; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
        }
        return $results;
    }
}
