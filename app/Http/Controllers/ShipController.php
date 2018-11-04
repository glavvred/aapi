<?php

namespace App\Http\Controllers;

use App\Ship;
use App\User;
use Illuminate\Http\Request;
use App\Planet;

/**
 * Class PlanetController
 * @package App\Http\Controllers
 */
class ShipController extends Controller
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
        $planetList = Planet::where('owner_id', $authId)->get();
        $res = [];
        foreach ($planetList as $planet) {
            $res[] = Planet::find($planet->id)->ships()->where('owner_id', $authId)
                ->groupBy('current_planet', 'type')
                ->get();
        }
        return response()->json($res, 200);
    }

    public function showFleetAtPlanet($request, $planetId) {
        $authId = $request->auth->id;
        $res = Planet::where($planetId)->ships->where('owner_id', $authId)
            ->where('current_planet', $planetId)
            ->groupBy('current_planet', 'type')
            ->get();
        return response()->json($res, 200);
    }



}