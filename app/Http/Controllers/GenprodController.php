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

        /**
         * Products imported on the site: active and flagged for web
         * (webitem = 1 and websale = 1), regardless of clasa/grupa.
         */
        public function onlySpa()
        {
            $spaItems = Genprod::query()
                ->where('activ', 1)
                ->where('webitem', 1)
                ->where('websale', 1)
                ->with('pret')
                ->get();
            return response()->json($spaItems);
        }

        /**
         * Web packages to import on the site (clasa/grupa from
         * ORDER_PACKAGE_FILTERS), with price (pret) and components (gencard).
         */
        public function packages(\App\Services\Orders\PackageBillService $service)
        {
            $packages = $service->packagesQuery()
                ->where('activ', 1)
                ->get()
                ->map(function (Genprod $package) use ($service) {
                    $data = $package->toArray();
                    unset($data['imagine']); // binary column, breaks JSON
                    $data['pret'] = $service->packagePrice($package);
                    $data['components'] = $service->components($package)->values();

                    return $data;
                });

            return response()->json($packages);
        }
}
