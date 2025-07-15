<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RoomSearchService
{
    public function searchAvailableRoomCombinations($adults, $kids, $startDate, $endDate, $numberOfRooms)
    {
        // Step 1: Get all rooms
        $rooms = DB::table('camerehotel')
            ->where('adultMax', '>=', $adults)
            ->where('kidMax', '>=', $kids)
            ->select('nr', 'adultMax', 'kidMax', 'tip', 'tiplung')
        ->get();

        $roomList = $rooms->map(function ($room) {
            return [
                'nr' => $room->nr,
                'adultMax' => $room->adultMax,
                'kidMax' => $room->kidMax,
                'type' => $room->tip,
                'typeName' => $room->tiplung,
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
                ->where(function ($query) use ($startDateTime, $endDateTime) {
                    $query->whereRaw('? <= datas AND ? >= dataf', [$startDateTime, $endDateTime]);
                })
                ->get();
            if ($reserved->isEmpty()) {
                $availableCombinations[] = $combo;
            }
        }
        return $this->groupRooms($availableCombinations);
    }

    private function groupRooms($availableCombinations): array
    {
        $newCombinations = [];
        foreach ($availableCombinations as $id => $combo) {
            $types = [];
            foreach ($combo as $room) {
                $types[] = $room['type'];
            }
            $newCombinations[ implode('-', $types)] ['combo'][] = $combo;

        }
        return $newCombinations;
    }

    // Helper to generate all combinations of rooms that meet the people requirement and number of rooms
    private function getRoomCombinations($rooms, $adults, $kids, $numberOfRooms)
    {
        $results = [];
        $n = count($rooms);
        $totalPeople = $adults + $kids;
        $r = min($numberOfRooms, $n);
        if ($r < 1)
            return $results;
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
