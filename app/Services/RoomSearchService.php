<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Camerehotel;
use Carbon\Carbon;

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
    public function searchAvailableRoomCombinations($adults, $kids, $startDate, $endDate, $numberOfRooms, $page = 1, $perPage = 10)
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
                'roomKey' => $this->buildRoomKey($room->idhotel, $room->nr),
                'price' => $room->pret[0]->pret ?? 0,
            ];
        })->toArray();


        // Step 2: Distribute people per room
        $distribution = $this->distributePeoplePerRoom($adults, $kids, $numberOfRooms);
        $start = Carbon::parse($startDate)->startOfDay();   // 2025-11-02 00:00:00
        $end   = Carbon::parse($endDate)->startOfDay();     // 2025-11-07 00:00:00 (limita exclusivă)

        $reservedRooms = DB::table('rezervarehotel')
            ->select('idhotel', 'camera')
            // suprapunere: [datas, dataf) cu [start, end)
            ->where('datas', '<', $end)
            ->where('dataf', '>', $start)
            ->distinct()              // evită duplicatele dacă o cameră are mai multe rânduri
            ->get();

        $reservedSet = [];
        foreach ($reservedRooms as $reservedRoom) {
            $reservedSet[$this->buildRoomKey($reservedRoom->idhotel, $reservedRoom->camera)] = true;
        }

        // Step 4: Generate all combinations of rooms with the specified number
        $combinations = $this->getRoomCombinations($roomList, $distribution, $numberOfRooms);

        // Step 5: Filter out combinations that contain any reserved room
        $availableCombinations = [];
        foreach ($combinations as $combo) {
            $hasReserved = false;
            foreach ($combo as $room) {
                if (isset($reservedSet[$room['roomKey']])) {
                    $hasReserved = true;
                    break;
                }
            }
            if (!$hasReserved) {
                $availableCombinations[] = $combo;
            }
        }
        $grouped = $this->groupRooms($availableCombinations);
        $ones = [];
        $twos = [];
        $mixed = [];
        foreach ($grouped as $item) {
            $hotelIds = array_unique(array_map(function ($room) {
                return (string) $room['hotel'];
            }, $item['combo'][0]));

            if (count($hotelIds) === 1 && $hotelIds[0] === '1') {
                $ones[] = $item;
            } elseif (count($hotelIds) === 1 && $hotelIds[0] === '2') {
                $twos[] = $item;
            } else {
                $mixed[] = $item;
            }
        }

        // Interleave ones and twos for balanced pages
        $interleaved = [];
        $max = max(count($ones), count($twos));
        for ($i = 0; $i < $max; $i++) {
            if (isset($twos[$i])) $interleaved[] = $twos[$i];
            if (isset($ones[$i])) $interleaved[] = $ones[$i];
        }
        // Add mixed at the end
        $finalArray = array_merge($interleaved, $mixed);

        $total = count($finalArray);
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($finalArray, $offset, $perPage);
        return [
            'data' => $paginated,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ];
    }

    private function groupRooms($availableCombinations): array
    {
        $newCombinations = [];
        foreach ($availableCombinations as  $combo) {
            $types = [];
            $price = 0;
            $hotels = [];
            foreach ($combo as $room) {
                $types[] = $room['type'];
                $price += $room['price'];
                $hotels[] = $room['hotel'];
            }
            $keyParts = [];
            foreach ($combo as $index => $room) {
                $keyParts[] = $hotels[$index] . ':' . $types[$index];
            }
            $key = implode('-', $keyParts);
            // Only keep the first combo for each type
         
            if (!isset($newCombinations[$key])) {
                $newCombinations[$key] = [
                    'combo' => [$combo],
                    'price_combo' => $price,
                ];
                $newCombinations[$key]['hotels'] = implode('', $hotels);
            }
        }

        uasort($newCombinations, function ($a, $b) {
            return $a['price_combo'] <=> $b['price_combo'];
        });

        return $newCombinations;
    }

    private function buildRoomKey($hotelId, $roomNumber): string
    {
        return $hotelId . ':' . $roomNumber;
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
