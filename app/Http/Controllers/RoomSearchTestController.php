<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RoomSearchTestController extends Controller
{
    public function showForm()
    {
        return view('room_search_test');
    }

    public function submitForm(Request $request)
    {
        $response = Http::post(url('/api/rooms/search-combinations'), [
            'adults' => $request->input('adults'),
            'kids' => $request->input('kids'),
            'number_of_rooms' => $request->input('number_of_rooms'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        return view('room_search_test', [
            'result' => $response->json(),
            'input' => $request->all(),
        ]);
    }
}
