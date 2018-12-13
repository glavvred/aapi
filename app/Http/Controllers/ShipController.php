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
use Illuminate\Support\Facades\Config;

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

    public function moveFleet(Request $request, int $fleetId, int $planetId, int $destinationId, int $orderType)
    {
        $res = [];

        $coordinates = Planet::whereIn('id', [$planetId, $destinationId])->get();

        $origin = $coordinates->find($planetId);
        $destination = $coordinates->find($destinationId);

        if (($destination->coordinateX != $origin->coordinateX) || ($destination->coordinateY != $origin->coordinateY))
            $destinationOrbit = 30;
        else
            $destinationOrbit = $destination->orbit;

        $this->moveToOrbit($request, $fleetId, $destinationOrbit, 'move');
        die;
        $fleet = Fleet::where('coordinate_id', $planetId)
            ->where('id', $fleetId)
            ->first();


        var_dump($origin->coordinateX);
        var_dump($origin->coordinateY);
        var_dump($origin->orbit);
        echo "\r\n";
        var_dump($destination->coordinateX);
        var_dump($destination->coordinateY);
        var_dump($destination->orbit);
        echo "\r\n";

        //calc distance
        $distance = round(sqrt($origin->coordinateX * $destination->coordinateX +
            $origin->coordinateY * $destination->coordinateY));
        echo 'distance units ';
        var_dump($distance);

        //calc travel cost
        echo 'cost in gas ';
        var_dump($distance * 1000);

        //check resources available
        echo 'gas ';
        var_dump($origin->gas);

        //calc max distance (capacity)
        foreach ($fleet->ships as $fleetShip) {
            foreach ($fleetShip->contains as $ship) {
                var_dump($ship->id);
                $shipProperties = app('App\Http\Controllers\ResourceController')
                    ->parseAll($request, 'ship', $ship, 1, $planetId);
                var_dump($shipProperties['properties']);

            }
        }

        //buy resources
        //lets go already!


        return response()->json($res, 200);
    }


    public function moveToOrbit(Request $request, int $fleetId, int $destinationOrbit, string $orderType)
    {

        $fleet = Fleet::find($fleetId)->first();

        $origin = $fleet->coordinate()->first();

        $destination = Planet::where('coordinateX', $origin->coordinateX)
            ->where('coordinateY', $origin->coordinateY)
            ->where('orbit', $destinationOrbit)
            ->first();

        $distance = abs($origin->orbit - $destinationOrbit);

//        var_dump($origin->orbit);
//        var_dump($destinationOrbit);
//        var_dump($distance);
        $shipsInFleet = $fleet->ships()->get();

        $fuelNeeded = 0;
        foreach ($shipsInFleet as $shipInFleet) {
            $ship = Ship::find($shipInFleet->ship_id)->first();
            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($request, 'ship', $ship, 1, $origin->id);

            $fuelNeeded += $resources['properties']['non-combat']['consumption'] *
                $shipInFleet->quantity * $distance *
                Config::get('constants.distance.interplanetary');
        }

        if ($fuelNeeded > $origin->gas)
            return response()->json(['status' => 'error', 'message' => 'not enough gas to flight'], 200);

        app('App\Http\Controllers\BuildingController')->buy($origin, ['gas' => $fuelNeeded]);


        return $distance;
    }

    /**
     * Recalculate fleet capacity
     * todo: Use on change
     * @param Request $request
     * @param int $planetId
     * @param int $fleetId
     * @return int
     */
    public function recalculateCapacity(Request $request, int $planetId, int $fleetId)
    {
        $sumCapacity = 0;
        $fleetShips = Fleet::find($fleetId)->ships;
        foreach ($fleetShips as $fleetShip) {
            $quantity = $fleetShip->quantity;

            foreach ($fleetShip->contains as $ship) {
                $x = 0;

                $json = json_decode($ship->properties)->{'non-combat'}->capacity;
                $const['base_capacity'] = $json->constant[0]->base_capacity;

                $string_processed = preg_replace_callback(
                    '~\{\$(.*?)\}~si',
                    function ($match) use ($const) {
                        return eval('return $const[\'' . $match[1] . '\'];');
                    },
                    $json->formula[0]->capacity);

                eval('$x = round(' . $string_processed . ");");

                $sumCapacity += $x * $quantity;

            }
        }
        return (int)$sumCapacity;
    }

    public function recalculateSpeed(Request $request, int $planetId, int $fleetId)
    {

        $leastSpeed = 9999;

        $fleetShips = Fleet::find($fleetId)->ships;
        foreach ($fleetShips as $fleetShip) {
            foreach ($fleetShip->contains as $ship) {

                $resources = app('App\Http\Controllers\ResourceController')->parseAll($request, "ship", $ship, 1, $planetId);
                $const = $resources['techAndBuilding'];
                $x = 0;

                $json = json_decode($ship->properties)->{'non-combat'}->speed;
                $const['base_speed'] = $json->constant[0]->base_speed;

                $string_processed = preg_replace_callback(
                    '~\{\$(.*?)\}~si',
                    function ($match) use ($const) {
                        return eval('return $const[\'' . $match[1] . '\'];');
                    },
                    $json->formula[0]->speed);

                eval('$x = round(' . $string_processed . ");");

                $leastSpeed = ($leastSpeed < $x) ? $leastSpeed : $x;

            }
        }
        return $leastSpeed;
    }

    public function loadFleet(Request $request, int $planetId, int $fleetId)
    {
        $resourcesToLoad = [
            'metal' => 10,
            'crystal' => 20,
            'gas' => 30,
        ];


    }

    public function unloadFleet()
    {
    }

    public function showOneFleet(Request $request, int $planetId, int $fleetId)
    {
        $res = [];

        $fleet = Fleet::where('coordinate_id', $planetId)
            ->where('id', $fleetId)
            ->first();

        foreach ($fleet->ships as $fleetShip) {
            foreach ($fleetShip->contains as $ship) {
                $shipProperties = app('App\Http\Controllers\ResourceController')
                    ->parseAll($request, 'ship', $ship, 1, $planetId);

                $combat = $shipProperties['properties']['combat'];

                $re = [
                    'shipId' => $ship->id,
                    'name' => $ship->i18n($request->auth->language)->name,
                    'description' => $ship->i18n($request->auth->language)->description,
                    'type' => $ship->type,
                    'race' => $ship->race,
                    'attack' => $combat['attack'],
                    'shield' => $combat['shield'],
                    'armor' => $combat['armor'],
                    'quantity' => $fleetShip->quantity,
                ];

                $res[] = $re;
            }
        }

        return response()->json($res, 200);
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

        //check que
        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, $planet);
        if (!empty($ref['ships']))
            $ref = $ref['ships'];

        $shipDetails = app('App\Http\Controllers\ResourceController')->parseAll($request, 'ship', $ship, 1, $planetId);

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

        //no ship of given type at planetId
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
            'metal' => $shipDetails['cost']['metal'] * $quantity,
            'crystal' => $shipDetails['cost']['crystal'] * $quantity,
            'gas' => $shipDetails['cost']['gas'] * $quantity,
        ];

        if (!app('App\Http\Controllers\BuildingController')->checkResourcesAvailable($planet, $resources))
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);

        app('App\Http\Controllers\BuildingController')->buy($planet, $resources);

        $timeToBuild = $shipDetails['cost']['time'] * $quantity;

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

        $ref = app('App\Http\Controllers\BuildingController')->refreshPlanet($request, Planet::find($planetId));

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
            $shipProperties = app('App\Http\Controllers\ResourceController')
                ->parseAll($request, 'ship', $shipAvailable, 1, $planetId);

            $re = [
                'shipId' => $shipAvailable->id,
                'name' => $shipAvailable->i18n($user->language)->name,
                'description' => $shipAvailable->i18n($user->language)->description,
                'type' => $shipAvailable->type,
                'race' => $shipAvailable->race,
                'cost' => $shipProperties['cost'],
                'production' => $shipProperties['production'],
                'requirements' => $shipProperties['requirements'],
                'upgrades' => $shipProperties['upgrades'],
                'properties' => $shipProperties['properties'],
            ];

            if (!empty($ref['ships'])) {
                if ($ref['ships']['shipId'] == $shipAvailable->id) {
                    $re['startTime'] = $ref['ships']['shipStartTime'];
//                $re['timeToBuild'] = $ref['ships']['shipOneTimeToBuild'] * $ref['ships']['shipQuantityQued'];
                    $re['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
                }
            }
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