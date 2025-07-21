<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Client::active();

        // Filter by name
        if ($request->has('name')) {
            $query->byName($request->input('name'));
        }

        // Filter by email
        if ($request->has('email')) {
            $query->byEmail($request->input('email'));
        }

        // Filter by phone
        if ($request->has('phone')) {
            $query->byPhone($request->input('phone'));
        }

        // Filter by VIP status
        if ($request->has('vip')) {
            $query->where('vip', $request->boolean('vip'));
        }

        // Filter by city
        if ($request->has('oras')) {
            $query->where('oras', 'like', '%' . $request->input('oras') . '%');
        }

        // Filter by company
        if ($request->has('idfirma')) {
            $query->where('idfirma', $request->input('idfirma'));
        }

        // Filter by CNP/CUI
        if ($request->has('cnpcui')) {
            $query->where('cnpcui', $request->input('cnpcui'));
        }

        // Filter by employee status
        if ($request->has('angajat')) {
            $query->where('angajat', $request->boolean('angajat'));
        }

        $clients = $query->paginate(15);

        return response()->json($clients);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cnpcui' => 'sometimes|string|max:20|unique:client,cnpcui',
            'den' => 'required|string|max:100',
            'prenume' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:100|unique:client,email',
            'tel' => 'sometimes|string|max:20',
            'mobilcontact' => 'sometimes|string|max:20',
            'adresa1' => 'sometimes|string|max:255',
            'adresa2' => 'sometimes|string|max:255',
            'oras' => 'sometimes|string|max:100',
            'judet' => 'sometimes|string|max:100',
            'tara' => 'sometimes|string|max:100',
            'datan' => 'sometimes|date',
            'sex' => 'sometimes|in:M,F',
            'discount' => 'sometimes|numeric|between:0,100',
            'vip' => 'sometimes|boolean',
            'activ' => 'sometimes|boolean',
            'angajat' => 'sometimes|boolean',
            'idfirma' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $clientData = $request->all();
        $clientData['datacreare'] = now();
        $clientData['activ'] = $clientData['activ'] ?? true;

        $client = Client::create($clientData);

        return response()->json([
            'message' => 'Client created successfully',
            'data' => $client
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $client = Client::with('rezervari')->find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        return response()->json($client);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'cnpcui' => 'sometimes|string|max:20|unique:client,cnpcui,' . $id . ',spaid',
            'den' => 'sometimes|string|max:100',
            'prenume' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:100|unique:client,email,' . $id . ',spaid',
            'tel' => 'sometimes|string|max:20',
            'mobilcontact' => 'sometimes|string|max:20',
            'adresa1' => 'sometimes|string|max:255',
            'adresa2' => 'sometimes|string|max:255',
            'oras' => 'sometimes|string|max:100',
            'judet' => 'sometimes|string|max:100',
            'tara' => 'sometimes|string|max:100',
            'datan' => 'sometimes|date',
            'sex' => 'sometimes|in:M,F',
            'discount' => 'sometimes|numeric|between:0,100',
            'vip' => 'sometimes|boolean',
            'activ' => 'sometimes|boolean',
            'angajat' => 'sometimes|boolean',
            'idfirma' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $client->update($request->all());

        return response()->json([
            'message' => 'Client updated successfully',
            'data' => $client
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        // Soft delete by setting activ = 0
        $client->update(['activ' => false]);

        return response()->json([
            'message' => 'Client deactivated successfully'
        ]);
    }

    /**
     * Search clients by various criteria
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->input('query');

        $clients = Client::active()
            ->where(function($q) use ($query) {
                $q->where('den', 'like', "%{$query}%")
                  ->orWhere('prenume', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('tel', 'like', "%{$query}%")
                  ->orWhere('mobilcontact', 'like', "%{$query}%")
                  ->orWhere('cnpcui', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get();

        return response()->json($clients);
    }

    /**
     * Get VIP clients
     */
    public function getVipClients()
    {
        $vipClients = Client::active()
            ->vip()
            ->orderBy('puncte', 'desc')
            ->get();

        return response()->json($vipClients);
    }

    /**
     * Update client VIP status
     */
    public function updateVipStatus(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vip' => 'required|boolean',
            'puncte' => 'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $client->update([
            'vip' => $request->boolean('vip'),
            'puncte' => $request->input('puncte', $client->puncte)
        ]);

        return response()->json([
            'message' => 'VIP status updated successfully',
            'data' => $client
        ]);
    }

    /**
     * Get client reservations
     */
    public function getReservations($id)
    {
        $client = Client::with(['rezervari' => function($query) {
            $query->orderBy('datas', 'desc');
        }])->find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        return response()->json([
            'client' => $client,
            'reservations' => $client->rezervari
        ]);
    }

    /**
     * Get client statistics
     */
    public function getStatistics($id)
    {
        $client = Client::with('rezervari')->find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        $rezervari = $client->rezervari;
        $stats = [
            'total_reservations' => $rezervari->count(),
            'total_spent' => $rezervari->sum('total'),
            'last_reservation' => $rezervari->sortByDesc('datas')->first(),
            'average_spending' => $rezervari->avg('total'),
            'loyalty_points' => $client->puncte ?? 0
        ];

        return response()->json([
            'client' => $client,
            'statistics' => $stats
        ]);
    }

    /**
     * Get employees
     */
    public function getEmployees()
    {
        $employees = Client::active()
            ->where('angajat', true)
            ->orderBy('den')
            ->get();

        return response()->json($employees);
    }

    /**
     * Update employee status
     */
    public function updateEmployeeStatus(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'angajat' => 'required|boolean',
            'profesie' => 'sometimes|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $client->update([
            'angajat' => $request->boolean('angajat'),
            'profesie' => $request->input('profesie', $client->profesie ?? null)
        ]);

        return response()->json([
            'message' => 'Employee status updated successfully',
            'data' => $client
        ]);
    }

    /**
     * Get clients by company
     */
    public function getCompanyClients($companyId)
    {
        $clients = Client::active()
            ->where('idfirma', $companyId)
            ->orderBy('den')
            ->get();

        return response()->json($clients);
    }
}
