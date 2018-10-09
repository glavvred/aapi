<?php

namespace App\Http\Controllers;

use App\User;
use App\Fleet;
use Illuminate\Http\Request;
use App\Planet;

/**
 * Class PlanetController
 * @package App\Http\Controllers
 */
class FleetController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function showMyFleet(Request $request){
        $authId = $request->auth->id;
        $res = Fleet::where('owner_id', $authId)
            ->groupBy('current_planet', 'type')
            ->get();
        return response()->json($res, 200);
    }

    public function showFleetAtPlanet($request, $planetId) {
        $authId = $request->auth->id;
        $res = Fleet::where('owner_id', $authId)
            ->where('current_planet', $planetId)
            ->groupBy('current_planet', 'type')
            ->get();
        return response()->json($res, 200);
    }

}