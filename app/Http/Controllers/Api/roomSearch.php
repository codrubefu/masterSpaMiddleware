<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClientService;
use App\Services\RoomSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class roomSearch extends Controller
{
    protected $clientService;
    protected $roomSearchService;

    public function __construct(ClientService $clientService, RoomSearchService $roomSearchService)
    {
        $this->clientService = $clientService;
        $this->roomSearchService = $roomSearchService;
    }

    public function searchAvailableRoomCombinations(Request $request)
    {
        $adults = (int) $request->input('adults', 1);
        $kids = (int) $request->input('kids', 0);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $numberOfRooms = (int) $request->input('number_of_rooms', 1);
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 10);

        $availableCombinations = $this->roomSearchService->searchAvailableRoomCombinations(
            $adults,
            $kids,
            $startDate,
            $endDate,
            $numberOfRooms,
            $page,
            $perPage
        );

        return response()->json($availableCombinations);
    }
}
