<?php

namespace App\Http\Controllers;

use App\Fleet;


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

    public function moveFleet($fleetId, $coordinateId, $order){
        $fleet = Fleet::find($fleetId)->first();
        $from = $fleet->origin_id;
    }
}