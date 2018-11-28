<?php

namespace App\Http\Controllers;

use App\Fleet;
use App\FleetShips;
use App\Planet;
use App\PlanetShip;
use App\Ship;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

    public function buildShip(Request $request, int $quantity, int $planetId, int $shipId)
    {
        //нашли планету
        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);

        //нашли корабль
        $ship = Ship::find($shipId);
        if (!$ship)
            return response()->json(['status' => 'error', 'message' => 'no ship found'], 403);

        $planetShipActual = $ship->getData($request, $planetId);

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);
        $ref = $ref['ships'][0];

        //que check
        if (!empty($ref['shipStartTime']) && !empty(($ref['shipQuantityQued'] > 0)))
            return response()->json(['status' => 'error',
                'message' => 'que is not empty',
                'shipQuantityQued' => $ref['shipQuantityQued'],
                'shipQuantityRemain' => $ref['shipQuantityRemain'],
                'oneShipBuildTime' => $ref['shipOneTimeToBuild'],
                'shipTimePassedFromLast' => $ref['shipTimePassedFromLast'],
                'fullQueTimeRemain' => $ref['shipOneTimeToBuild'] * $ref['shipQuantityRemain'] - $ref['shipTimePassedFromLast'],
            ], 403);

        $planetShip = PlanetShip::where('planet_id', $planetId)
            ->where('ship_id', $shipId)
            ->first();

        if (empty($planetShip->id)) {
            $newShip = new PlanetShip;
            $newShip->ship_id = $shipId;
            $newShip->planet_id = $planetId;
            $newShip->save();

            $planetShip = PlanetShip::where('planet_id', $planetId)
                ->where('ship_id', $shipId)
                ->first();
        }

        $resources = [
            'metal' => $planetShipActual['cost']['metal'] * $quantity,
            'crystal' => $planetShipActual['cost']['crystal'] * $quantity,
            'gas' => $planetShipActual['cost']['gas'] * $quantity,
        ];

        if (!app('App\Http\Controllers\BuildingController')->checkResourcesAvailable($planet, $resources))
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);

        app('App\Http\Controllers\BuildingController')->buy($planet, $resources);

        $timeToBuild = $planetShipActual['cost']['time'] * $quantity;

        $planetShip->quantityQued = $quantity;
        $planetShip->quantity = $quantity;
        $planetShip->startTime = Carbon::now()->format('Y-m-d H:i:s');
        $planetShip->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $planetShip->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $planetShip->timeToBuildOne = $timeToBuild;
        $planetShip->save();

        return response()->json(['status' => 'success',
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'quantity' => $quantity,
            'fullShipTimeRemain' => $timeToBuild,
        ], 200);
    }


    /**
     * Show all my fleet
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMyFleet(Request $request)
    {
        $res = [];
        $authId = $request->auth->id;
        $fleetList = Fleet::where('owner_id', $authId)->get();

        foreach ($fleetList as $fleet) {
            //fleet props
            $res[$fleet->coordinate_id][$fleet->id] = [
                'ownerId' => $fleet->owner_id,
                'captain' => $fleet->captain, //todo: hired for
            ];

            foreach ($fleet->ships as $ship) {
                foreach ($ship->contains as $item) {
                    //ships in fleet props
                    $res[$fleet->coordinate_id][$fleet->id]['ships'][] = [
                        'shipId' => $item->id,
                        'name' => $item->i18n()->name,
                        'quantity' => $ship->quantity,
                    ];
                }
            }
        }

        return response()->json($res, 200);
    }

    /**
     * Show my fleet by planetId
     * @param $request
     * @param $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showFleetAtPlanet(Request $request, $planetId)
    {
        $res = [];

        $user = User::find($request->auth->id);

        $authId = $request->auth->id;
        $alliance = User::where('alliance_id', $request->auth->alliance_id)->get();
        $fleetList = Fleet::where('coordinate_id', $planetId)->get();
        $belongTo = '';

        foreach ($fleetList as $fleet) {
            //fleet props
            if ($fleet->owner_id == $authId)
                $belongTo = 'self';
            elseif ($alliance->contains($fleet->owner_id))
                $belongTo = 'alliance';

            $res[$fleet->coordinate_id][$belongTo][$fleet->owner_id][$fleet->id] = [
                'captain' => $fleet->captain
            ]; //todo: hired for

            foreach ($fleet->ships as $fleetShip) {
                foreach ($fleetShip->contains as $item) {
                    if (!empty($item->id)) {
                        //ships in fleet props
                        $res[$fleet->coordinate_id][$belongTo][$fleet->owner_id][$fleet->id]['ships'][] = [
                            'shipId' => $item->id,
                            'name' => $item->i18n($user->language)->name,
                            'quantity' => $fleetShip->quantity,
                        ];
                    }
                }
            }
        }

        return response()->json($res, 200);
    }

    public function showShipListByPlanet(Request $request, $planetId)
    {
        $res = [];

        $authId = $request->auth->id;
        $user = User::find($authId);
        //only my fleet counts
        $fleetList = Fleet::where('coordinate_id', $planetId)
            ->where('owner_id', $authId)
            ->get();

        $shipsAtPlanet = [];

        foreach ($fleetList as $fleet) {
            foreach ($fleet->ships as $ship) {
                if (!empty($shipsAtPlanet[$ship->ship_id]))
                    $shipsAtPlanet[$ship->ship_id] += $ship->quantity;
                else
                    $shipsAtPlanet[$ship->ship_id] = $ship->quantity;
            }
        }

        $shipsAvailable = Ship::where('race', $request->auth->race)->get();

        foreach ($shipsAvailable as $shipAvailable) {
            $re = [
                'shipId' => $shipAvailable->id,
                'name' => $shipAvailable->i18n($user->language)->name,
                'description' => $shipAvailable->i18n($user->language)->description,
                'type' => $shipAvailable->type,
                'race' => $shipAvailable->race,
                'attack' => $shipAvailable->attack,
                'defence' => $shipAvailable->defence,
                'shield' => $shipAvailable->shield,
                'speed' => $shipAvailable->speed,
            ];

            if (!empty($shipsAtPlanet[$shipAvailable->id]))
                $re['quantity'] = $shipsAtPlanet[$shipAvailable->id];
            else
                $re['quantity'] = 0;

            $res[] = $re;
        }

        return response()->json($res, 200);
    }

    /**
     * Show enemy fleet by planetId
     * @param $request
     * @param $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showEnemyFleetAtPlanet($request, $planetId)
    {
        $authId = $request->auth->id;
        $res = Planet::where($planetId)->ships->where('owner_id', $authId)
            ->where('current_planet', $planetId)
            ->groupBy('current_planet', 'type')
            ->get();
        return response()->json($res, 200);
    }


}