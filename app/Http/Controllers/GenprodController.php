<?php

namespace App\Http\Controllers;

use App\Models\Genprod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GenprodController extends Controller
{
    public function index()
    {
        return Genprod::all();
    }

    public function show($id)
    {
        $genprod = Genprod::find($id);
        if (!$genprod) {
            return response()->json(['message' => 'Not found'], 404);
        }
        return $genprod;
    }

    public function store(Request $request)
    {
        $genprod = Genprod::create($request->all());
        return response()->json($genprod, 201);
    }

    public function update(Request $request, $id)
    {
        $genprod = Genprod::find($id);
        if (!$genprod) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $genprod->update($request->all());
        return response()->json($genprod);
    }

    public function destroy($id)
    {
        $genprod = Genprod::find($id);
        if (!$genprod) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $genprod->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

        // Get only Genprod records where clasa = 'SPA'
        public function onlySpa()
        {
          
            $spaItems = Genprod::whereIn('clasa', ['PACHETE', 'ABONAMENTE'])->with('pret')->get();
            return response()->json($spaItems);
        }
}
