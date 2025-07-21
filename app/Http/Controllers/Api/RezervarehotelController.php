<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rezervarehotel;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RezervarehotelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Rezervarehotel::active()->with('client');

        // Filter by client ID
        if ($request->has('idcl')) {
            $query->where('idcl', $request->input('idcl'));
        }

        // Filter by room number
        if ($request->has('camera')) {
            $query->where('camera', $request->input('camera'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by hotel ID
        if ($request->has('idhotel')) {
            $query->where('idhotel', $request->input('idhotel'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forDateRange($request->input('start_date'), $request->input('end_date'));
        }

        // Filter by check-in status
        if ($request->has('checkin')) {
            $query->where('checkin', $request->boolean('checkin'));
        }

        // Filter by check-out status
        if ($request->has('checkout')) {
            $query->where('checkout', $request->boolean('checkout'));
        }

        // Filter by payment status
        if ($request->has('platit_status')) {
            if ($request->input('platit_status') === 'paid') {
                $query->where('platit', '>=', \DB::raw('total'));
            } elseif ($request->input('platit_status') === 'unpaid') {
                $query->where('platit', '<', \DB::raw('total'));
            }
        }

        // Filter by company
        if ($request->has('idfirma')) {
            $query->where('idfirma', $request->input('idfirma'));
        }

        // Sort by date
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy('datas', $sortDirection);

        $reservations = $query->paginate(15);

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idcl' => 'required|integer|exists:client,spaid',
            'datas' => 'required|date',
            'dataf' => 'required|date|after:datas',
            'camera' => 'required|string|max:20',
            'tipcamera' => 'sometimes|string|max:50',
            'nradulti' => 'required|integer|min:1',
            'nrcopii' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|max:20',
            'tipmasa' => 'sometimes|string|max:20',
            'prettipmasa' => 'sometimes|numeric|min:0',
            'pachet' => 'sometimes|string|max:50',
            'pretcamera' => 'required|numeric|min:0',
            'pretmasa' => 'sometimes|numeric|min:0',
            'pretpachet' => 'sometimes|numeric|min:0',
            'pretextra' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'pretnoapte' => 'sometimes|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'idhotel' => 'sometimes|integer',
            'idfirma' => 'sometimes|integer',
            'obsdatas' => 'sometimes|string|max:255',
            'obsdataf' => 'sometimes|string|max:255',
            'agent' => 'sometimes|string|max:100',
            'tip' => 'sometimes|string|max:20',
            'idrezgrup' => 'sometimes|integer',
            'clheadrez' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check room availability
        $existingReservation = Rezervarehotel::active()
            ->where('camera', $request->input('camera'))
            ->forDateRange($request->input('datas'), $request->input('dataf'))
            ->first();

        if ($existingReservation) {
            return response()->json([
                'message' => 'Room is not available for the selected dates',
                'conflicting_reservation' => $existingReservation->idrezervarehotel
            ], 409);
        }

        // Calculate number of nights
        $startDate = Carbon::parse($request->input('datas'));
        $endDate = Carbon::parse($request->input('dataf'));
        $nights = $endDate->diffInDays($startDate);

        $reservationData = $request->all();
        $reservationData['nrnopti'] = $nights;
        $reservationData['data'] = now();
        $reservationData['status'] = $reservationData['status'] ?? 'confirmed';
        $reservationData['sters'] = false;
        $reservationData['checkin'] = false;
        $reservationData['checkout'] = false;
        $reservationData['platit'] = $reservationData['platit'] ?? 0;
        $reservationData['utilizator'] = auth()->user()->name ?? 'system';
        $reservationData['idloginuser'] = auth()->id() ?? null;

        $reservation = Rezervarehotel::create($reservationData);

        return response()->json([
            'message' => 'Reservation created successfully',
            'data' => $reservation->load('client')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $reservation = Rezervarehotel::with('client')->find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        return response()->json($reservation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $reservation = Rezervarehotel::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        if ($reservation->sters) {
            return response()->json([
                'message' => 'Cannot update deleted reservation'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'idcl' => 'sometimes|integer|exists:client,spaid',
            'datas' => 'sometimes|date',
            'dataf' => 'sometimes|date|after:datas',
            'camera' => 'sometimes|string|max:20',
            'tipcamera' => 'sometimes|string|max:50',
            'nradulti' => 'sometimes|integer|min:1',
            'nrcopii' => 'sometimes|integer|min:0',
            'status' => 'sometimes|string|max:20',
            'tipmasa' => 'sometimes|string|max:20',
            'prettipmasa' => 'sometimes|numeric|min:0',
            'pachet' => 'sometimes|string|max:50',
            'pretcamera' => 'sometimes|numeric|min:0',
            'pretmasa' => 'sometimes|numeric|min:0',
            'pretpachet' => 'sometimes|numeric|min:0',
            'pretextra' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'pretnoapte' => 'sometimes|numeric|min:0',
            'total' => 'sometimes|numeric|min:0',
            'platit' => 'sometimes|numeric|min:0',
            'idhotel' => 'sometimes|integer',
            'idfirma' => 'sometimes|integer',
            'obsdatas' => 'sometimes|string|max:255',
            'obsdataf' => 'sometimes|string|max:255',
            'agent' => 'sometimes|string|max:100',
            'tip' => 'sometimes|string|max:20',
            'idrezgrup' => 'sometimes|integer',
            'clheadrez' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check room availability if dates or room changed
        if ($request->has('camera') || $request->has('datas') || $request->has('dataf')) {
            $camera = $request->input('camera', $reservation->camera);
            $datas = $request->input('datas', $reservation->datas);
            $dataf = $request->input('dataf', $reservation->dataf);

            $conflictingReservation = Rezervarehotel::active()
                ->where('camera', $camera)
                ->where('idrezervarehotel', '!=', $id)
                ->forDateRange($datas, $dataf)
                ->first();

            if ($conflictingReservation) {
                return response()->json([
                    'message' => 'Room is not available for the selected dates',
                    'conflicting_reservation' => $conflictingReservation->idrezervarehotel
                ], 409);
            }
        }

        // Recalculate nights if dates changed
        if ($request->has('datas') || $request->has('dataf')) {
            $startDate = Carbon::parse($request->input('datas', $reservation->datas));
            $endDate = Carbon::parse($request->input('dataf', $reservation->dataf));
            $request->merge(['nrnopti' => $endDate->diffInDays($startDate)]);
        }

        $reservation->update($request->all());

        return response()->json([
            'message' => 'Reservation updated successfully',
            'data' => $reservation->load('client')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $reservation = Rezervarehotel::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        // Soft delete
        $reservation->update([
            'sters' => true,
            'datadel' => now(),
            'utilizatordel' => auth()->user()->name ?? 'system',
            'idlogindel' => auth()->id() ?? null
        ]);

        return response()->json([
            'message' => 'Reservation deleted successfully'
        ]);
    }

    /**
     * Check-in a reservation
     */
    public function checkIn(Request $request, $id)
    {
        $reservation = Rezervarehotel::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        if ($reservation->checkin) {
            return response()->json([
                'message' => 'Reservation already checked in'
            ], 400);
        }

        $reservation->update([
            'checkin' => true,
            'datacheckin' => now(),
            'status' => 'checked_in'
        ]);

        return response()->json([
            'message' => 'Check-in successful',
            'data' => $reservation->load('client')
        ]);
    }

    /**
     * Check-out a reservation
     */
    public function checkOut(Request $request, $id)
    {
        $reservation = Rezervarehotel::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        if (!$reservation->checkin) {
            return response()->json([
                'message' => 'Cannot check-out without check-in'
            ], 400);
        }

        if ($reservation->checkout) {
            return response()->json([
                'message' => 'Reservation already checked out'
            ], 400);
        }

        $reservation->update([
            'checkout' => true,
            'datacheckout' => now(),
            'status' => 'completed'
        ]);

        return response()->json([
            'message' => 'Check-out successful',
            'data' => $reservation->load('client')
        ]);
    }

    /**
     * Update payment for reservation
     */
    public function updatePayment(Request $request, $id)
    {
        $reservation = Rezervarehotel::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'platit' => 'required|numeric|min:0',
            'payment_method' => 'sometimes|string|max:50',
            'payment_notes' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reservation->update([
            'platit' => $request->input('platit')
        ]);

        $paymentStatus = $reservation->platit >= $reservation->total ? 'fully_paid' : 'partially_paid';

        return response()->json([
            'message' => 'Payment updated successfully',
            'data' => $reservation->load('client'),
            'payment_status' => $paymentStatus,
            'remaining_amount' => max(0, $reservation->total - $reservation->platit)
        ]);
    }

    /**
     * Get reservations for a specific room and date range
     */
    public function getRoomReservations(Request $request, $roomNumber)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reservations = Rezervarehotel::active()
            ->with('client')
            ->where('camera', $roomNumber)
            ->forDateRange($request->input('start_date'), $request->input('end_date'))
            ->orderBy('datas')
            ->get();

        return response()->json([
            'room' => $roomNumber,
            'date_range' => [
                'start' => $request->input('start_date'),
                'end' => $request->input('end_date')
            ],
            'reservations' => $reservations
        ]);
    }

    /**
     * Get reservations statistics
     */
    public function getStatistics(Request $request)
    {
        $query = Rezervarehotel::active();

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->forDateRange($request->input('start_date'), $request->input('end_date'));
        }

        $stats = [
            'total_reservations' => $query->count(),
            'total_revenue' => $query->sum('total'),
            'total_paid' => $query->sum('platit'),
            'pending_payments' => $query->whereRaw('platit < total')->sum(\DB::raw('total - platit')),
            'checked_in_count' => $query->where('checkin', true)->where('checkout', false)->count(),
            'completed_count' => $query->where('checkout', true)->count(),
            'cancelled_count' => Rezervarehotel::where('sters', true)->count(),
            'average_stay_duration' => $query->avg('nrnopti'),
            'occupancy_by_status' => $query->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
        ];

        return response()->json($stats);
    }

    /**
     * Cancel a reservation
     */
    public function cancel(Request $request, $id)
    {
        $reservation = Rezervarehotel::find($id);

        if (!$reservation) {
            return response()->json([
                'message' => 'Reservation not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'motiv_anulare' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reservation->update([
            'status' => 'cancelled',
            'motivdel' => $request->input('motiv_anulare')
        ]);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'data' => $reservation->load('client')
        ]);
    }

    /**
     * Get today's arrivals
     */
    public function getTodaysArrivals()
    {
        $today = Carbon::today();

        $arrivals = Rezervarehotel::active()
            ->with('client')
            ->whereDate('datas', $today)
            ->where('checkin', false)
            ->orderBy('datas')
            ->get();

        return response()->json([
            'date' => $today->toDateString(),
            'count' => $arrivals->count(),
            'arrivals' => $arrivals
        ]);
    }

    /**
     * Get today's departures
     */
    public function getTodaysDepartures()
    {
        $today = Carbon::today();

        $departures = Rezervarehotel::active()
            ->with('client')
            ->whereDate('dataf', $today)
            ->where('checkin', true)
            ->where('checkout', false)
            ->orderBy('dataf')
            ->get();

        return response()->json([
            'date' => $today->toDateString(),
            'count' => $departures->count(),
            'departures' => $departures
        ]);
    }
}
