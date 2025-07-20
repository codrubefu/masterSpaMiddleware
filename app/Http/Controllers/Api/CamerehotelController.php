<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camerehotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CamerehotelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Camerehotel::query();

        // Filter by hotel
        if ($request->has('idhotel')) {
            $query->byHotel($request->input('idhotel'));
        }

        // Filter by type
        if ($request->has('tip')) {
            $query->byType($request->input('tip'));
        }

        // Filter by floor
        if ($request->has('etajresel')) {
            $query->byFloor($request->input('etajresel'));
        }

        // Filter by virtual status
        if ($request->has('virtual')) {
            $query->virtual($request->boolean('virtual'));
        }

        // Filter by minimum capacity
        if ($request->has('adults') || $request->has('kids')) {
            $adults = $request->input('adults', 0);
            $kids = $request->input('kids', 0);
            $query->minCapacity($adults, $kids);
        }

        // Filter by total capacity
        if ($request->has('total_capacity')) {
            $query->totalCapacity($request->input('total_capacity'));
        }

        // Filter by baby bed availability
        if ($request->boolean('baby_bed')) {
            $query->withBabyBed();
        }

        // Filter by room number
        if ($request->has('nr')) {
            $query->where('nr', 'like', '%' . $request->input('nr') . '%');
        }

        $rooms = $query->paginate(15);

        return response()->json($rooms);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nr' => 'required|string|max:50|unique:camerehotel,nr',
            'virtual' => 'sometimes|boolean',
            'idlabel' => 'sometimes|integer',
            'tip' => 'required|integer',
            'tiplung' => 'sometimes|string|max:100',
            'pagina' => 'sometimes|integer',
            'idhotel' => 'required|integer',
            'etajresel' => 'sometimes|integer',
            'nrcamresel' => 'sometimes|integer',
            'etajhk' => 'sometimes|integer',
            'idtabletahk' => 'sometimes|integer',
            'locknr' => 'sometimes|integer',
            'adultMax' => 'required|integer|min:1',
            'kidMax' => 'required|integer|min:0',
            'babyBed' => 'sometimes|integer|min:0',
            'bed' => 'sometimes|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $room = Camerehotel::create($request->all());

        return response()->json([
            'message' => 'Room created successfully',
            'data' => $room
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $room = Camerehotel::with('rezervari')->find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Room not found'
            ], 404);
        }

        return response()->json($room);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $room = Camerehotel::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Room not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nr' => 'sometimes|string|max:50|unique:camerehotel,nr,' . $id . ',idcamerehotel',
            'virtual' => 'sometimes|boolean',
            'idlabel' => 'sometimes|integer',
            'tip' => 'sometimes|integer',
            'tiplung' => 'sometimes|string|max:100',
            'pagina' => 'sometimes|integer',
            'idhotel' => 'sometimes|integer',
            'etajresel' => 'sometimes|integer',
            'nrcamresel' => 'sometimes|integer',
            'etajhk' => 'sometimes|integer',
            'idtabletahk' => 'sometimes|integer',
            'locknr' => 'sometimes|integer',
            'adultMax' => 'sometimes|integer|min:1',
            'kidMax' => 'sometimes|integer|min:0',
            'babyBed' => 'sometimes|integer|min:0',
            'bed' => 'sometimes|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $room->update($request->all());

        return response()->json([
            'message' => 'Room updated successfully',
            'data' => $room
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $room = Camerehotel::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Room not found'
            ], 404);
        }

        $room->delete();

        return response()->json([
            'message' => 'Room deleted successfully'
        ]);
    }

    /**
     * Get rooms by hotel
     */
    public function getRoomsByHotel($hotelId)
    {
        $rooms = Camerehotel::byHotel($hotelId)
            ->orderBy('etajresel')
            ->orderBy('nr')
            ->get();

        return response()->json($rooms);
    }

    /**
     * Get rooms by type
     */
    public function getRoomsByType($type)
    {
        $rooms = Camerehotel::byType($type)
            ->orderBy('nr')
            ->get();

        return response()->json($rooms);
    }

    /**
     * Get rooms by floor
     */
    public function getRoomsByFloor($hotelId, $floor)
    {
        $rooms = Camerehotel::byHotel($hotelId)
            ->byFloor($floor)
            ->orderBy('nr')
            ->get();

        return response()->json($rooms);
    }

    /**
     * Search available rooms for capacity
     */
    public function searchByCapacity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'adults' => 'required|integer|min:1',
            'kids' => 'sometimes|integer|min:0',
            'idhotel' => 'sometimes|integer',
            'baby_bed' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Camerehotel::minCapacity(
            $request->input('adults'),
            $request->input('kids', 0)
        );

        if ($request->has('idhotel')) {
            $query->byHotel($request->input('idhotel'));
        }

        if ($request->boolean('baby_bed')) {
            $query->withBabyBed();
        }

        $rooms = $query->orderBy('nr')->get();

        return response()->json($rooms);
    }

    /**
     * Get room statistics
     */
    public function getRoomStatistics($id)
    {
        $room = Camerehotel::with('rezervari')->find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Room not found'
            ], 404);
        }

        $stats = [
            'room_number' => $room->nr,
            'type' => $room->tiplung,
            'total_capacity' => $room->total_capacity,
            'adult_capacity' => $room->adultMax,
            'kid_capacity' => $room->kidMax,
            'baby_beds' => $room->babyBed,
            'beds' => $room->bed,
            'floor' => $room->etajresel,
            'is_virtual' => $room->is_virtual,
            'has_baby_bed' => $room->has_baby_bed,
            'total_reservations' => $room->rezervari->count(),
            'current_reservations' => $room->rezervari()
                ->where('datas', '<=', now())
                ->where('dataf', '>=', now())
                ->count()
        ];

        return response()->json([
            'room' => $room,
            'statistics' => $stats
        ]);
    }

    /**
     * Get all virtual rooms
     */
    public function getVirtualRooms()
    {
        $virtualRooms = Camerehotel::virtual(true)
            ->orderBy('nr')
            ->get();

        return response()->json($virtualRooms);
    }

    /**
     * Get rooms with baby beds
     */
    public function getRoomsWithBabyBeds()
    {
        $roomsWithBabyBeds = Camerehotel::withBabyBed()
            ->orderBy('nr')
            ->get();

        return response()->json($roomsWithBabyBeds);
    }
}
