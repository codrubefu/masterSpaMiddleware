<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Camerehotel;

class RoomSearchService
{
    // Distribute adults and kids as evenly as possible per room
    private function distributePeoplePerRoom($adults, $kids, $numberOfRooms)
    {
        $adultsPerRoom = array_fill(0, $numberOfRooms, intdiv($adults, $numberOfRooms));
        $kidsPerRoom = array_fill(0, $numberOfRooms, intdiv($kids, $numberOfRooms));

        for ($i = 0; $i < $adults % $numberOfRooms; $i++) {
            $adultsPerRoom[$i]++;
        }
        for ($i = 0; $i < $kids % $numberOfRooms; $i++) {
            $kidsPerRoom[$i]++;
        }

        $distribution = [];
        for ($i = 0; $i < $numberOfRooms; $i++) {
            $distribution[] = [
                'adults' => $adultsPerRoom[$i],
                'kids' => $kidsPerRoom[$i],
            ];
        }
        return $distribution;
    }
    public function searchAvailableRoomCombinations($adults, $kids, $startDate, $endDate, $numberOfRooms)
    {
        // Step 1: Get all rooms
        $rooms = Camerehotel::with('pret')
            ->select('nr', 'adultMax', 'kidMax', 'tip', 'tiplung', 'idhotel')
            ->get();

        $roomList = $rooms->map(function ($room) {
            return [
                'nr' => $room->nr,
                'adultMax' => $room->adultMax,
                'kidMax' => $room->kidMax,
                'type' => $room->tip,
                'typeName' => $room->tiplung,
                'hotel' => $room->idhotel,
                'price' => $room->pret[0]->pret ?? 0,
            ];
        })->toArray();

        // Format input dates to match DB format
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';

        // Step 2: Distribute people per room
        $distribution = $this->distributePeoplePerRoom($adults, $kids, $numberOfRooms);

        // Step 3: Pre-fetch all reserved room numbers for the date range
        $reservedRooms = DB::table('rezervarehotel')
            ->where(function ($query) use ($startDateTime, $endDateTime) {
                $query->whereRaw('datas <= ? AND dataf >= ?', [$startDateTime, $endDateTime]);
            })
            ->pluck('camera')
            ->toArray();
        $reservedSet = array_flip($reservedRooms); // for fast lookup

        // Step 4: Generate all combinations of rooms with the specified number
        $combinations = $this->getRoomCombinations($roomList, $distribution, $numberOfRooms);

        // Step 5: Filter out combinations that contain any reserved room
        $availableCombinations = [];
        foreach ($combinations as $combo) {
            $hasReserved = false;
            foreach ($combo as $room) {
                if (isset($reservedSet[$room['nr']])) {
                    $hasReserved = true;
                    break;
                }
            }
            if (!$hasReserved) {
                $availableCombinations[] = $combo;
            }
        }
        return $this->groupRooms($availableCombinations);
    }

    private function groupRooms($availableCombinations): array
    {
        $newCombinations = [];
        foreach ($availableCombinations as  $combo) {
            $types = [];
            $price = 0;
            $hotel = '';
            foreach ($combo as $room) {
                $types[] = $room['type'];
                $price += $room['price'];
                $hotel .= $room['hotel'];
            }
            $key = implode('-', $types);
            // Only keep the first combo for each type
         
            if (!isset($newCombinations[$key])) {
                $newCombinations[$key] = [
                    'combo' => [$combo],
                    'price_combo' => $price,
                ];
                $newCombinations[$key]['hotels'] = $hotel;
            }
        }

        uasort($newCombinations, function ($a, $b) {
            return $a['price_combo'] <=> $b['price_combo'];
        });

        return $newCombinations;
    }

    // Helper to generate all combinations of rooms that meet the per-room people distribution
    private function getRoomCombinations($rooms, $distribution, $numberOfRooms)
    {
        $results = [];
        $n = count($rooms);
        $r = min($numberOfRooms, $n);
        if ($r < 1)
            return $results;
        $indices = range(0, $r - 1);
        while (true) {
            $combo = [];
            $valid = true;
            foreach ($indices as $idx => $i) {
                $room = $rooms[$i];
                $adultsNeeded = $distribution[$idx]['adults'];
                $kidsNeeded = $distribution[$idx]['kids'];
                if ($room['adultMax'] < $adultsNeeded || $room['kidMax'] < $kidsNeeded) {
                    $valid = false;
                    break;
                }
                $combo[] = $room;
            }
            if ($valid) {
                $results[] = $combo;
            }
            // Next combination
            $i = $r - 1;
            while ($i >= 0 && $indices[$i] == $n - $r + $i)
                $i--;
            if ($i < 0)
                break;
            $indices[$i]++;
            for ($j = $i + 1; $j < $r; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
        }
        return $results;
    }
}
