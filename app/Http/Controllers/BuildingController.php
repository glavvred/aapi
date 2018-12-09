<?php

namespace App\Http\Controllers;

use App\Building;
use App\Planet;
use App\Technology;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuildingController extends Controller
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

    /**
     * Show building details
     * For level 1+ show current building stats
     *
     * @param Request $request
     * @param int $id Planet
     * @param int $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneBuilding(Request $request, int $id, int $bid)
    {
        $user = User::find($request->auth->id);
        $building = Building::where('id', $bid)->first();

        //building info
        $res = [
            'id' => $building->id,
            'name' => $building->i18n($user->language)->name,
            'description' => $building->i18n($user->language)->description,
            'type' => $building->type,
            'race' => $building->race,
        ];

        $planet = Planet::find($id);
        $ref = $this->refreshPlanet($request, $planet);
        //fresh buildings data
        $buildingAtUser = $planet->buildings->find($bid);

        $resources = [];

        if (!empty($building->resources) || !empty($building->requirements) || !empty($building->upgrades)) {
            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($building, 'building', $buildingAtUser->pivot->level);
        }

        $res['resources'] = [
            'current' => $resources['cost'],
            'current_per_hour' => $resources['production'],
        ];

        //actual building at planet
        if (!empty($buildingAtUser)) {
            $res['level'] = $buildingAtUser->pivot->level;
            $res['startTime'] = $buildingAtUser->pivot->startTime;
            $res['timeToBuild'] = $buildingAtUser->pivot->timeToBuild;
            $res['destroying'] = $buildingAtUser->pivot->destroying;
            $res['updated_at'] = $buildingAtUser->pivot->updated_at;
        }
        return response()->json($res);
    }

    /**
     * Refresh planet data
     * Counts ques, updates buildings, technologies if it is done
     * Counts resources from buildings, updates planet resources by time delta * resources per hour
     *
     * @param $request Request
     * @param Planet $planet
     * @return array
     */
    public function refreshPlanet(Request $request, Planet $planet)
    {
        $owner = $request->auth->id;
        $user = User::find($owner);

        $buildingStartTime = $buildingTimeToBuild = $buildingTimeRemain = $buildingsQued = 0;

        $techStartTime = $techTimeToBuild = $techQueTimeRemain = $techStatus = 0;

        $shipTime = [];

        $overallMetalPH = 0;
        $overallCrystalPH = 0;
        $overallGasPH = 0;
        $overallEnergyAvailable = 0;
        $overallEnergyUsed = 0;

        //строения
        foreach ($planet->buildings as $building) {
            $buildingByPivot = $building->pivot;

            //todo: актуальная формула
            $overallMetalPH += round($building->metal_ph * pow(1.15, $buildingByPivot->level));
            $overallCrystalPH += round($building->crystal_ph * pow(1.15, $buildingByPivot->level));
            $overallGasPH += round($building->gas_ph * pow(1.15, $buildingByPivot->level));
            $energy = round($building->energy_ph * pow(1.15, $buildingByPivot->level));
            if ($energy > 0)
                $overallEnergyAvailable += $energy;
            else
                $overallEnergyUsed += $energy;

            //update ques
            if (!empty($buildingByPivot->startTime)) {
                $endTime = Carbon::parse($buildingByPivot->startTime)->addSecond($buildingByPivot->timeToBuild);

                if (Carbon::now()->diffInSeconds($endTime, false) <= 0) {

                    //что то достроилось
                    $add = 0;
                    if ($buildingByPivot->startTime)
                        $add = ($buildingByPivot->destroying) ? '-1' : 1;

                    $planet->buildings()->updateExistingPivot($building->id, [
                        'level' => $buildingByPivot->level + ($add),
                        'startTime' => null,
                        'timeToBuild' => null,
                        'destroying' => 0,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
                } else {
                    $buildingStartTime = $buildingByPivot->startTime;
                    $buildingTimeToBuild = $buildingByPivot->timeToBuild;
                    $buildingTimeRemain = Carbon::now()->diffInSeconds($endTime);
                    $buildingsQued++;
                }
            }
        }

        //технологии
        foreach ($user->technologies as $technology) {
            $techByPivot = $technology->pivot;
            if ($techByPivot->planet_id == $planet->id) {
                if (!empty($techByPivot->startTime) && !(empty($techByPivot->timeToBuild))) {
                    //update ques
                    $techEndTime = Carbon::parse($techByPivot->startTime)->addSecond($techByPivot->timeToBuild);

                    if (Carbon::now()->diffInSeconds($techEndTime, false) <= 0) {
                        $techStatus = 0;

                        //что то достроилось
                        $user->technologies()->updateExistingPivot($technology->id, [
                            'level' => $techByPivot->level + 1,
                            'planet_id' => null,
                            'startTime' => null,
                            'timeToBuild' => null,
                            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
                    } else {
                        $techStartTime = $techByPivot->startTime;
                        $techTimeToBuild = $techByPivot->timeToBuild;
                        $techQueTimeRemain = Carbon::now()->diffInSeconds($techEndTime);
                        $techStatus = 1;
                    }
                }
            }
        }

        //корабли
        $ships = $planet->ships()->get();
        foreach ($ships as $ship) {
            $shipByPivot = $ship->pivot;
            if (!empty($shipByPivot->startTime) && !(empty($shipByPivot->timeToBuildOne))) {

                $diff = Carbon::parse($shipByPivot->updated_at)
                    ->subSeconds($shipByPivot->passedFromLastOne)
                    ->diffInSeconds(Carbon::now(), true);

                //количество целых за текущий отрезок
                $quantityBuilt = floor($diff / $shipByPivot->timeToBuildOne);
                if ($quantityBuilt > $shipByPivot->quantityQued)
                    $quantityBuilt = $shipByPivot->quantityQued;

                //остаток от деления
                $timePassed = $diff % $shipByPivot->timeToBuildOne;

                $fleet = $planet->fleets()->where('owner_id', $request->auth->id)->first();
                //нет флота игрока на планете
                if (is_null($fleet)) {
                    DB::table('fleets')->insert([
                        'owner_id' => $request->auth->id,
                        'coordinate_id' => $planet->id,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
                    $fleet = $planet->fleets()->where('owner_id', $request->auth->id)->first();
                }

                //     planet     fleetsC    fleet  fleetShips
                $fs = $fleet->ships->filter(function ($item) use ($ship) {
                    return $item->ship_id == $ship->id;
                })->first();

                //нет кораблей в флоте на планете
                if (is_null($fs)) {
                    DB::table('fleet_ships')->insert([
                        'fleet_id' => $fleet->id,
                        'ship_id' => $ship->id,
                        'quantity' => 0,
                    ]);
                    $fs = $fleet->ships->filter(function ($item) use ($ship) {
                        return $item->ship_id == $ship->id;
                    })->first();
                }

                if ($shipByPivot->quantity > $quantityBuilt) {
                    //что то достроилось, но еще остались корабли в очереди

                    //добавим к первому флоту на этой планете количество построенного
                    $fs->increment('quantity', $quantityBuilt);

                    $planet->ships()->updateExistingPivot($ship->id, [
                        'quantity' => $shipByPivot->quantity - $quantityBuilt,
                        'passedFromLastOne' => $timePassed,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

                    $shipStartTime = $shipByPivot->startTime;
                    $shipQuantityQued = $shipByPivot->quantityQued;
                    $shipTimePassedFromLast = $timePassed;

                    $shipTimeRemain = Carbon::parse($shipByPivot->startTime)
                        ->addSeconds($shipQuantityQued * $shipByPivot->timeToBuildOne)
                        ->diffInSeconds(Carbon::now(), false);
                    $shipQuantityRemain = $shipByPivot->quantity - $quantityBuilt;

                    $shipTime[] = [
                        'shipStartTime' => $shipStartTime,
                        'shipOneTimeToBuild' => $shipByPivot->timeToBuildOne,
                        'shipTimeRemain' => $shipTimeRemain,
                        'shipTimePassedFromLast' => $shipTimePassedFromLast,
                        'shipQuantityQued' => $shipQuantityQued,
                        'shipQuantityRemain' => $shipQuantityRemain,
                    ];


                } else {
                    //все достроилось

                    $shipTime[] = [
                        'shipStartTime' => null,
                        'shipTimeRemain' => null,
                        'shipOneTimeToBuild' => null,
                        'shipTimePassedFromLast' => null,
                        'shipQuantityQued' => null,
                        'shipQuantityRemain' => null,
                    ];

                    $planet->ships()->updateExistingPivot($ship->id, [
                        'startTime' => null,
                        'quantity' => null,
                        'quantityQued' => null,
                        'timeToBuildOne' => null,
                        'passedFromLastOne' => null,
                        'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
                }
            }
        }

        //update resources
        $diff = Carbon::now()->diffInSeconds(Carbon::parse($planet->updated_at));
        $planet->increment('metal', round($overallMetalPH * ($diff / 60)));
        $planet->increment('crystal', round($overallCrystalPH * ($diff / 60)));
        $planet->increment('gas', round($overallGasPH * ($diff / 60)));
        $planet->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $planet->save();

        return ['resources' => [
            'metal' => $planet->metal,
            'crystal' => $planet->crystal,
            'gas' => $planet->gas,
            'metalPh' => $planet->metal_ph,
            'crystalPh' => $planet->crystal_ph,
            'gasPh' => $planet->gas_ph,
            'energyAvailable' => $overallEnergyAvailable,
            'energyUsed' => $overallEnergyUsed,
        ],
            'buildingStartTime' => $buildingStartTime,
            'buildingQued' => $buildingsQued,
            'buildingTimeToBuild' => $buildingTimeToBuild,
            'buildingTimeRemain' => $buildingTimeRemain,

            'technologyTimeToBuild' => $techTimeToBuild,
            'techQueTimeRemain' => $techQueTimeRemain,
            'techQued' => $techStatus,
            'techStartTime' => $techStartTime,

            'ships' => $shipTime,
        ];
    }

    /**
     * Show buildings list by planetId
     *
     * @param Request $request
     * @param int $planetId Planet
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllBuildings(Request $request, int $planetId)
    {
        $planet = Planet::find($planetId);
        $user = User::find($request->auth->id);

        $bap = DB::table('buildings-with-lang as buildings')
            ->select('planets.id',
                'planet_building.planet_id as pivot_planet_id',
                'planet_building.building_id as pivot_building_id',
                'planet_building.level as pivot_level',
                'planet_building.startTime as pivot_startTime',
                'planet_building.timeToBuild as pivot_timeToBuild',
                'planet_building.updated_at as pivot_updated_at',
                'planet_building.destroying as pivot_destroying',
                'buildings.*')
            ->leftJoin(DB::raw(
                    "(select * from `planet_building` where `planet_building`.`planet_id` = $planetId) planet_building"),
                function ($join) {
                    $join->on('planet_building.building_id', '=', 'buildings.id');
                })
            ->leftJoin('planets', 'planets.id', '=', 'planet_building.planet_id')
            ->where('buildings.race', '=', $user->race)
            ->where('buildings.language', '=', $user->language)
            ->get();

        //актуализация данных по планете
        $ref = $this->refreshPlanet($request, $planet);

        $res = [];

        foreach ($bap as $building) {
            //building info
            $res[$building->id] = [
                'id' => $building->id,
                'name' => $building->name,
                'description' => $building->description, //->i18n($user->language)
                'type' => $building->type,
                'race' => $building->race,
            ];

            if (empty($building->pivot_level))
                $building->pivot_level = 0;

            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($request, 'building', $building, $building->pivot_level, $planetId);

            $res[$building->id]['resources'] = [
                'current' => $resources['cost'],
                'current_per_hour' => $resources['production'],
            ];
            $res[$building->id]['requirements'] = $resources['requirements'];

            //actual building at planet
            if (!empty($building->pivot_level)) {
                $res[$building->id]['level'] = $building->pivot_level;
                $res[$building->id]['startTime'] = $building->pivot_startTime;
                $res[$building->id]['timeToBuild'] = $building->pivot_timeToBuild;
                $res[$building->id]['destroying'] = $building->pivot_destroying;
                $res[$building->id]['updated_at'] = $building->pivot_updated_at;
            }
        }

        return response()->json($res);
    }

    /**
     * Add one level to given building
     * Add level 0 if no building exists
     * Resource, slots check
     *
     * @param $request Request
     * @param int $planetId Planet
     * @param int $buildingId Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        //нашли планету
        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);
        $ref = $this->refreshPlanet($request, $planet);

        //que check
        if ($ref['buildingQued'] && ($ref['buildingTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => 'que is not empty', 'time_remain' => $ref['buildingTimeRemain']], 403);

        $slots = $this->slotsAvailable($planet);

        if (!$slots['canBuild'])
            return response()->json(['status' => 'error', 'message' => 'no planet slots available', 'time_remain' => $ref['buildingTimeRemain']], 403);

        $building = Building::find($buildingId);

        //если нет на планете - создали с уровнем 0
        $buildingAtPlanet = $planet->buildings()->where('building_id', $buildingId)->first();

        if (is_null($buildingAtPlanet)) {
            $planet->buildings()->attach($buildingId);
            $buildingAtPlanet = $planet->buildings()->find($buildingId);
        }

        $level = !empty($buildingAtPlanet->pivot->level) ? $buildingAtPlanet->pivot->level : 0;

        //resources check
        $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($request, 'building', $building, $level + 1, $planetId);

//        var_dump($resourcesAtLevel);

        if (!$this->checkResourcesAvailable($planet, $resourcesAtLevel['cost']))
            return response()->json(['status' => 'error', 'message' => 'no resources'], 403);

        $this->buy($planet, $resourcesAtLevel['cost']);

        $timeToBuild = $resourcesAtLevel['cost']['time'];

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'level' => $level,
            'destroying' => 0,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $level + 1,
            'time' => $timeToBuild], 200);
    }

    /**
     * Slots checker by given planet
     * @param Planet $planet
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function slotsAvailable(Planet $planet)
    {

        $bap = $planet->buildings;
        $occupied = 0;
        foreach ($bap as $building) {
            $occupied += ($building->pivot->level);
        }
        return ['available' => $planet->slots, 'occupied' => $occupied, 'canBuild' => ($planet->slots > $occupied)];
    }

    /**
     * Simple resource checker
     *
     * @param $planet Planet
     * @param array $resourcesToCheck
     * @return bool
     * @throws \BadMethodCallException
     */
    public function checkResourcesAvailable(Planet $planet, array $resourcesToCheck)
    {
        if ($planet &&
            ($resourcesToCheck['metal'] <= $planet->metal) &&
            ($resourcesToCheck['crystal'] <= $planet->crystal) &&
            ($resourcesToCheck['gas'] <= $planet->gas)
        )
            return true;
        return false;
    }

    /**
     * Remove given amount of resources from given planet
     *
     * @param Planet $planet
     * @param array $resources
     * @return void
     */
    public function buy(Planet $planet, array $resources)
    {
        try {
            DB::beginTransaction();
            $planet = DB::table('planets')->where('id', $planet->id)->lockForUpdate()->first();
            DB::table('planets')->where('id', $planet->id)
                ->update([
                    'metal' => $planet->metal - $resources['metal'],
                    'crystal' => $planet->crystal - $resources['crystal'],
                    'gas' => $planet->gas - $resources['gas']
                ]);
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            var_dump($exception->getMessage());
        }
    }

    public function cancelBuilding(Request $request, int $bid, int $planetId)
    {
        //нашли планету
        $planet = Planet::find($planetId);
        $user = User::find($request->auth->id);

        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);
        if ($planet->owner_id != $user->id)
            return response()->json(['status' => 'error', 'message' => 'not your planet'], 403);

        $ref = $this->refreshPlanet($request, $planet);

        //slots check
        if (!$ref['buildingQued'] || ($ref['queTimeRemain'] == 0))
            return response()->json(['status' => 'error', 'message' => 'no buildings qued'], 403);

        $building = Building::find($bid);
        $buildingAtPlanet = $planet->buildings()->find($bid);

        //resources refund
        $refund = $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($building, 'building', $buildingAtPlanet->pivot->level, $buildingAtPlanet, $planetId);

        $this->buy($planet, $refund);

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'startTime' => null,
            'timeToBuild' => null,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        $planet = Planet::find($planetId);

        $ref = $this->refreshPlanet($request, $planet);

        return response()->json(['status' => 'success',
            'message' => 'building removed from que',
            'building' => [
                'id' => $building->id,
                'name' => $building->i18n($user->language)->name,
                'current_level' => $buildingAtPlanet->pivot->level,
            ],
            'buildingQued' => $ref['buildingQued'],
            'queTimeRemain' => $ref['queTimeRemain'],

            'refunded' => [
                'metal' => $refund['metal'],
                'crystal' => $refund['crystal'],
                'gas' => $refund['gas'],

            ],
        ], 200);

    }

    /**
     * Remove one level from given building
     * Error if building current level is 0
     * Slots check, resources refund
     *
     * @param $request Request
     * @param int $planetId
     * @param int $buildingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function downgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => 'no planet found'], 403);

        $ref = $this->refreshPlanet($request, $planet);
        //slots check
        if ($ref['buildingQued'] && ($ref['queTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => 'no slots available', 'time_remain' => $ref['queTimeRemain']], 403);

        $buildingAtPlanet = $planet->buildings()->where('building_id', $buildingId)->first();
        if (!$buildingAtPlanet || ($buildingAtPlanet->pivot->level <= 0))
            return response()->json(['status' => 'error', 'message' => 'building is lvl 0'], 403);

        //resources refund
        $resources = [
            'metal' => -$buildingAtPlanet->cost_metal,
            'crystal' => -$buildingAtPlanet->cost_crystal,
            'gas' => -$buildingAtPlanet->cost_gas,
            'dark_matter' => -$buildingAtPlanet->cost_dark_matter,
            'time' => -$buildingAtPlanet->cost_time,
        ];
        $resourcesAtLevel = $this->calcLevelResourceCost($buildingAtPlanet->pivot->level - 1, $resources);

        //todo: slots check

        $this->buy($planet, $resourcesAtLevel);

        $timeToBuild = $this->calcLevelTimeCost($buildingAtPlanet->pivot->level - 1, $buildingAtPlanet->cost_time);


        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'level' => $buildingAtPlanet->pivot->level - 1,
            'destroying' => 1,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $buildingAtPlanet->pivot->level - 1,
            'timeToBuild' => $this->calcLevelTimeCost($buildingAtPlanet->pivot->level - 1, $buildingAtPlanet->cost_time)
        ], 200);
    }

    /**
     * TODO: move to Resource class
     * @param $level
     * @param array $levelOneResources
     * @return mixed
     */
    public function calcLevelResourceCost($level, array $levelOneResources)
    {
        $res['metal'] = round($levelOneResources['metal'] * pow(1.55, $level));
        $res['crystal'] = round($levelOneResources['crystal'] * pow(1.55, $level));
        $res['gas'] = round($levelOneResources['gas'] * pow(1.55, $level));
        $res['dark_matter'] = round($levelOneResources['dark_matter'] * pow(1.55, $level));
        $res['time'] = $this->calcLevelTimeCost($level, $levelOneResources['time']);
        return $res;
    }

    /**
     * TODO: move to Resource class
     *
     * @param $level
     * @param $levelOneTimeCost
     * @return float
     */
    public function calcLevelTimeCost($level, $levelOneTimeCost)
    {
        return round($levelOneTimeCost * pow(1.55, $level));
    }

    /**
     * TODO: move to Resource class
     * @param $level
     * @param $formula
     */
    public function calcLevelResourcePh($level, $formula)
    {

    }

}
