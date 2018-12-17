<?php

namespace App\Http\Controllers;

use App\Fleet;
use App\FleetShips;
use App\Planet;
use App\PlanetShip;
use App\Ship;
use App\User;
use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;


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

        var_dump($this->moveToOrbit($request, $fleetId, $destinationOrbit, 1));

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
                    ->parseAll(User::find($request->auth->id), $ship, 1, $planetId);
                var_dump($shipProperties['properties']);

            }
        }

        //buy resources
        //lets go already!


        return response()->json($res, 200);
    }


    /**
     * Move fleet to given orbit
     * @uses i18n
     * @param Request $request
     * @param int $fleetId
     * @param int $planetId
     * @param int $destinationOrbit
     * @param int $orderType
     * @return \Illuminate\Http\JsonResponse
     */
    public function moveToOrbit(Request $request, int $fleetId, int $planetId, int $destinationOrbit, int $orderType)
    {

        $cargoMetal = $cargoCrystal = $cargoGas = 0;
        $language = $request->auth->language;
        $fleet = Fleet::find($fleetId);

        $origin = $fleet->coordinate;

        $destination = Planet::where('coordinateX', $origin->coordinateX)
            ->where('coordinateY', $origin->coordinateY)
            ->where('orbit', $destinationOrbit)
            ->firstOrNew([
                'coordinateX' => $origin->coordinateX,
                'coordinateY' => $origin->coordinateY,
                'orbit' => $destinationOrbit,
            ]);

        if (empty($destination->name)) {
            $destination->name = $origin->coordinateX . ':' . $origin->coordinateY . ':' . $destinationOrbit;
            $destination->slots = 0;
            $destination->temperature = 0;
            $destination->diameter = 0;
            $destination->density = 0;
            $destination->galaxy = 1;
            $destination->type = 1;
            $destination->metal = 0;
            $destination->crystal = 0;
            $destination->gas = 0;
            $destination->created_at = Carbon::now();
            $destination->save();
            $destination->refresh();
        }

        $distance = abs($origin->orbit - $destinationOrbit);

        $shipsInFleet = $fleet->ships()->get();

        $fuelNeeded = $droneCount = $droneCapacity = 0;

        $ships = [];

        foreach ($shipsInFleet as $shipInFleet) {
            $ship = Ship::find($shipInFleet->ship_id);
            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll(User::find($request->auth->id), $ship, 1, $origin->id);

            //drone capacity
            if (empty($resources['properties']['non-combat']['capacity_ship']))
                $droneCount += $shipInFleet->quantity;
            else
                $droneCapacity += $shipInFleet->quantity * $resources['properties']['non-combat']['capacity_ship'];

            //fuel
            $fuelNeeded += $resources['properties']['non-combat']['consumption'] *
                $shipInFleet->quantity *
                $distance *
                Config::get('constants.distance.interplanetary');

            $ships[$ship->name] = [$shipInFleet->quantity, $resources['properties']['non-combat']['consumption']];
        }

        if ($fuelNeeded > $origin->gas)
            return response()->json(['status' => 'error',
                'fuelNeeded' => $fuelNeeded,
                'fleet' => $fleet,
                'message' => MessagesController::i18n('gas_not_enough', $language)], 200);

        if ($fuelNeeded + $cargoMetal + $cargoCrystal + $cargoGas > $fleet->overall_capacity) {
            $res = '';
            foreach ($ships as $name => $cons) {
                $res .= $name . ' -> (' . $cons[0] . ' * ' . $cons[1] . ') ';
            }

            return response()->json(['status' => 'error',
                'message' => MessagesController::i18n('capacity_not_enough_to_flight', $language),
                'fleet' => $fleet,
                'fuelNeededExplained' => MessagesController::i18n('ships', $language) . ': ' . $res . " * " . MessagesController::i18n('distance', $language) . ": " . $distance . " = " . $fuelNeeded,
                'fuelNeeded' => $fuelNeeded,
                'cargoLoaded' => [
                    'metal' => $fleet->metal,
                    'crystal' => $fleet->crystal,
                    'gas' => $fleet->gas,
                ],
                'fleetCapacity' => $fleet->overall_capacity
            ], 200);
        }
        if ($droneCount > $droneCapacity)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('drone_capacity_not_enough_to_flight', $language)], 200);

//        $routes = $fleet->routes();
//        foreach ($routes as $route) {
//            app(RouteController::class)->getCollisions($route);
//        }
//
//        die;
        RouteController::ladder($request, $fleet, $destination, $orderType);
die;
        return response()->json(['status' => 'success', 'message' => MessagesController::i18n('fleet_dispatched', $language)], 200);

    }

    public function makeRouteNear(Fleet $fleet, $destination)
    {
        $originOrbit = $fleet->coordinate->orbit;
        $destinationOrbit = $destination->orbit;

        $coordinates = [];

        if ($destinationOrbit > $originOrbit) {
            for ($i = $originOrbit; $i < $destinationOrbit + 1; $i++) {
                $coordinates[] = $i;
            }
        } else {
            for ($i = $originOrbit; $i > $destinationOrbit - 1; $i--) {
                $coordinates[] = $i;
            }
        }
        return $coordinates;
    }

    public function checkCollision(Fleet $fleet, array $route)
    {

    }

    public function refreshFleet()
    {

    }

    /**
     * Burn some gas on ship move
     * @param Fleet $fleet
     * @param int $amount
     */
    public function burnGas(Fleet $fleet, int $amount)
    {
        $fleet->gas -= $amount;
        $fleet->save();
    }

    /**
     * Recalculate fleet capacity
     * Used on model change
     * @param int $fleetId
     * @return int
     */
    public function recalculateCapacity(int $fleetId)
    {
        $sumCapacity = 0;
        $fleet = Fleet::find($fleetId);
        $user = User::find($fleet->owner_id);
        $fleetShips = $fleet->ships;

        foreach ($fleetShips as $fleetShip) {
            $quantity = $fleetShip->quantity;
            foreach ($fleetShip->contains as $ship) {
                $planet_id = $fleet->coordinate_id;
                $resources = app('App\Http\Controllers\ResourceController')->parseAll($user, $ship, 1, $planet_id);
                $sumCapacity += $quantity * $resources['properties']['non-combat']['capacity'];
            }
        }
        return intval($sumCapacity);
    }

    /**
     * Recalculate minimum speed of ships in fleet
     * Used on model change
     * @param int $fleetId
     * @return int
     */
    public function recalculateSpeed(int $fleetId)
    {
        $leastSpeed = 10000000;
        $fleet = Fleet::find($fleetId);
        $user = User::find($fleet->owner_id);
        $fleetShips = $fleet->ships;

        foreach ($fleetShips as $fleetShip) {
            foreach ($fleetShip->contains as $ship) {
                $resources = app('App\Http\Controllers\ResourceController')->parseAll($user, $ship, 1, $fleet->coordinate_id);
                $speed = $resources['properties']['non-combat']['speed'];
                $leastSpeed = ($leastSpeed < $speed) ? $leastSpeed : $speed;
            }
        }
        return intval($leastSpeed);
    }

    /**
     * Resources tranfer.
     * Positive quantity = planet -> fleet
     * Negative quantity = fleet -> planet.
     * @uses i18n
     * @param Request $request
     * @param int $fleetId
     * @return Response
     */
    public function transferResourcesToFleet(Request $request, int $fleetId)
    {
        $fleet = Fleet::findOrFail($fleetId);

        $language = $request->auth->language;

        $metal = $request->input('metal');
        $crystal = $request->input('crystal');
        $gas = $request->input('gas');

        if ($fleet->owner()->id != $request->auth->id)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('origin_fleet_not_your', $language)], 200);

        if (($metal +
                $fleet->metal +
                $crystal +
                $fleet->crystal +
                $gas +
                $fleet->gas) > $fleet->overall_capacity)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('fleet_capacity_exceeded', $language)], 200);

        $planet = $fleet->coordinate;

        if (!empty($metal)) {
            if (($metal > 0 && $planet->metal >= $metal) || ($metal < 0 && $fleet->metal >= -$metal)) {
                $planet->metal -= $metal;
                $fleet->metal += $metal;
            } else
                return response()->json(['status' => 'error', 'message' => MessagesController::i18n('metal_not_enough', $language)], 200);
        }

        if (!empty($crystal)) {
            if (($crystal > 0 && $planet->crystal >= $crystal) || ($crystal < 0 && $fleet->crystal >= -$crystal)) {
                $planet->crystal -= $crystal;
                $fleet->crystal += $crystal;
            } else
                return response()->json(['status' => 'error', 'message' => MessagesController::i18n('crystal_not_enough', $language)], 200);
        }

        if (!empty($gas)) {
            if (($gas > 0 && $planet->gas >= $gas) || ($gas < 0 && $fleet->gas >= -$gas)) {
                $planet->gas -= $gas;
                $fleet->gas += $gas;
            } else
                return response()->json(['status' => 'error', 'message' => MessagesController::i18n('crystal_not_enough', $language)], 200);
        }

        $planet->save();
        $fleet->save();

        return response()->json(['status' => 'success', 'message' => MessagesController::i18n('resources_transfer_success', $language)], 200);

    }

    /**
     * Transfer ships from fleet1 to fleet2
     * Positive quantity = fleet1 -> fleet2
     * Negative quantity = fleet2 -> fleet1.
     * @uses i18n
     * @param Request $request
     * @param int $fleetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferShipsToFleet(Request $request, int $fleetId)
    {
        $fleet = Fleet::findOrFail($fleetId);
        $language = $request->auth->language;
        if ($fleet->owner_id != $request->auth->id)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('origin_fleet_not_your', $language)], 200);

        $shipId = $request->input('shipId');
        $targetFleetId = $request->input('targetFleet');
        $quantity = $request->input('quantity');

        $targetFleet = Fleet::findOrFail($targetFleetId);
        if ($targetFleet->owner_id != $request->auth->id)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('target_fleet_not_your', $language)], 200);
        if ($targetFleet->coordinate_id != $fleet->coordinate_id)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('fleet_coordinates_not_match', $language)], 200);

        $shipInOriginFleet = $fleet->ships->where('ship_id', $shipId)->first();
        if (is_null($shipInOriginFleet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('no_ships_of_given_type_in_fleet', $language)], 200);

        if ($shipInOriginFleet->quantity < $quantity)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('not_enough_ships', $language)], 200);

        $shipInTargetFleet = $targetFleet->ships->where('ship_id', $shipId)->first();

        if ($quantity < 0) {
            if (is_null($shipInTargetFleet))
                return response()->json(['status' => 'error', 'message' => MessagesController::i18n('no_ships_of_given_type_in_fleet', $language)], 200);

            if ($shipInTargetFleet->quantity < -$quantity)
                return response()->json(['status' => 'error', 'message' => MessagesController::i18n('not_enough_ships', $language)], 200);
        }

        //нет заданных кораблей в конечном флоте
        if (is_null($shipInTargetFleet)) {
            DB::table('fleet_ships')->insert([
                'fleet_id' => $targetFleetId,
                'ship_id' => $shipId,
                'quantity' => 0,
            ]);
            //refresh
            $targetFleet = $targetFleet->refresh();
            $shipInTargetFleet = $targetFleet->ships->where('ship_id', $shipId)->first();
        }

        $shipInOriginFleet->quantity -= $quantity;
        $shipInTargetFleet->quantity += $quantity;

        //fleet params recalc on update
        $shipInOriginFleet->save();
        $shipInTargetFleet->save();

        //get updated fleet capacity
        $fleet->refresh();

        if ($fleet->overall_capacity < $fleet->metal + $fleet->crystal + $fleet->gas) {
            //rollback ships
            $shipInOriginFleet->quantity += $quantity;
            $shipInTargetFleet->quantity -= $quantity;

            $shipInOriginFleet->save();
            $shipInTargetFleet->save();

            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('capacity_insufficien_after_transfer', $language)], 200);
        }

        return response()->json(['status' => 'success', 'message' => MessagesController::i18n('ships_transfer_success', $language)], 200);
    }

    /**
     * Show one fleet by id
     * @param Request $request
     * @param int $planetId
     * @param int $fleetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneFleet(Request $request, int $planetId, int $fleetId)
    {
        $res = [];

        $fleet = Fleet::where('coordinate_id', $planetId)
            ->where('id', $fleetId)
            ->first();

        foreach ($fleet->ships as $fleetShip) {
            foreach ($fleetShip->contains as $ship) {
                $user = User::find($fleetShip->fleet()->owner_id);
                $shipProperties = app('App\Http\Controllers\ResourceController')
                    ->parseAll($user, $ship, 1, $planetId);

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

    /**
     * Add ship to building que
     * @param Request $request
     * @param int $quantity
     * @param int $planetId
     * @param int $shipId
     * @return \Illuminate\Http\JsonResponse
     */
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

        $shipDetails = app('App\Http\Controllers\ResourceController')->parseAll(User::find($planet->owner_id), $ship, 1, $planetId);

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

    /**
     * Show ships by planet with respective quantity
     * @param Request $request
     * @param $planetId
     * @return \Illuminate\Http\JsonResponse
     */
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
                ->parseAll($user, $shipAvailable, 1, $planetId);

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