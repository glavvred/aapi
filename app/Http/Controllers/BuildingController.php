<?php

namespace App\Http\Controllers;

use App\Building;
use App\FleetShip;
use App\Planet;
use App\Ship;
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
     * @uses i18n
     * @param Request $request
     * @param int $id Planet
     * @param int $bid Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOneBuilding(Request $request, int $id, int $bid)
    {
        $user = User::find($request->auth->id);
        $language = $user->language;

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
        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

        $ref = $this->refreshPlanet($request, $planet);
        //fresh buildings data
        $buildingAtUser = $planet->buildings->find($bid);

        $resources = [];

        if (!empty($building->resources) || !empty($building->requirements) || !empty($building->upgrades)) {
            $resources = app('App\Http\Controllers\ResourceController')
                ->parseAll($user, $building, $buildingAtUser->pivot->level, $id);
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
     * @uses i18n
     * @param $request Request
     * @param Planet $planet
     * @return array
     */
    public function refreshPlanet(Request $request, Planet $planet)
    {
        $language = $request->auth->language;

        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

        $owner = User::find($planet->owner_id);

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

            $resources = app('App\Http\Controllers\ResourceController')->parseAll($owner, $building, 1, $planet->id);

            $overallMetalPH += $resources['production']['metal'];
            $overallCrystalPH += $resources['production']['crystal'];
            $overallGasPH += $resources['production']['gas'];
            $energy = $resources['production']['energy'];

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
        foreach ($owner->technologies as $technology) {
            $techByPivot = $technology->pivot;
            if ($techByPivot->planet_id == $planet->id) {
                if (!empty($techByPivot->startTime) && !(empty($techByPivot->timeToBuild))) {
                    //update ques
                    $techEndTime = Carbon::parse($techByPivot->startTime)->addSecond($techByPivot->timeToBuild);

                    if (Carbon::now()->diffInSeconds($techEndTime, false) <= 0) {
                        $techStatus = 0;

                        //что то достроилось
                        $owner->technologies()->updateExistingPivot($technology->id, [
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
                        'origin_id' => $planet->id,
                        'overall_speed' => 0,
                        'overall_capacity' => 0,
                        'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
                    $fleet = $planet->fleets()->where('owner_id', $request->auth->id)->first();
                }

                $fs = $fleet->ships->where('ship_id', $ship->id)->first();

                //нет заданных кораблей в флоте на планете
                if (is_null($fs)) {
                    DB::table('fleet_ships')->insert([
                        'fleet_id' => $fleet->id,
                        'ship_id' => $ship->id,
                        'quantity' => 0,
                    ]);
                    //refresh
                    $fleet = $fleet->refresh();
                    $fs = $fleet->ships->where('ship_id', $ship->id)->first();
                }

                //добавим к первому флоту на этой планете количество построенного
                $fs->quantity = $fs->quantity + $quantityBuilt;
                $fs->save();

                if ($shipByPivot->quantity > $quantityBuilt) {
                    //что то достроилось, но еще остались корабли в очереди

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

                    $shipTime = [
                        'shipId' => $ship->id,
                        'planetId' => $planet->id,
                        'shipStartTime' => $shipStartTime,
                        'shipOneTimeToBuild' => $shipByPivot->timeToBuildOne,
                        'shipTimeRemain' => $shipTimeRemain,
                        'shipTimePassedFromLast' => $shipTimePassedFromLast,
                        'shipQuantityQued' => $shipQuantityQued,
                        'shipQuantityRemain' => $shipQuantityRemain,
                    ];
                } else {
                    //все достроилось
                    $shipTime = [
                        'shipId' => null,
                        'planetId' => null,
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
            'metalPh' => $overallMetalPH,
            'crystalPh' => $overallCrystalPH,
            'gasPh' => $overallGasPH,
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
     * @uses i18n
     * @param Request $request
     * @param int $planetId Planet
     * @return \Illuminate\Http\JsonResponse
     */
    public function showAllBuildings(Request $request, int $planetId)
    {
        $language = $request->auth->language;
        $planet = Planet::find($planetId);

        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

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
                ->parseAll($user, $building, $building->pivot_level, $planetId);

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
     * @uses i18n
     * @param $request Request
     * @param int $planetId Planet
     * @param int $buildingId Building
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        $language = $request->auth->language;

        //нашли планету
        $planet = Planet::find($planetId);

        if (empty($planet))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 200);

        $user = User::find($planet->owner_id);

        $ref = $this->refreshPlanet($request, $planet);

        //que check
        if ($ref['buildingQued'] && ($ref['buildingTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' =>  MessagesController::i18n('building_que_not_empty', $language), 'time_remain' => $ref['buildingTimeRemain']], 403);

        $slots = $this->slotsAvailable($planet);

        if (!$slots['canBuild'])
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_slots_insufficient', $language), 'time_remain' => $ref['buildingTimeRemain']], 403);

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
            ->parseAll($user, $building, $level + 1, $planetId);

        if (!$this->checkResourcesAvailable($planet, $resourcesAtLevel['cost']))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('no_resources', $language)], 403);

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
     * @param bool $refund
     * @return void
     */
    public function buy(Planet $planet, array $resources, $refund = false)
    {
        if (empty($resources['metal']))
            $resources['metal'] = 0;
        if (empty($resources['crystal']))
            $resources['crystal'] = 0;
        if (empty($resources['gas']))
            $resources['gas'] = 0;

        if ($refund) {
            $resources['metal'] = -$resources['metal'];
            $resources['crystal'] = -$resources['crystal'];
            $resources['gas'] = -$resources['gas'];
        }

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

    /**
     * Cancel building request
     *
     * @uses i18n
     * @param Request $request
     * @param int $bid
     * @param int $planetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelBuilding(Request $request, int $bid, int $planetId)
    {
        $language = $request->auth->language;

        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 403);

        $user = User::find($request->auth->id);
        $owner = User::find($planet->owner_id);

        if ($user != $owner)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_yours', $language)], 403);

        $ref = $this->refreshPlanet($request, $planet);

        //slots check
        if (!$ref['buildingQued'] || ($ref['queTimeRemain'] == 0))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_que_empty', $language)], 403);

        $building = Building::find($bid);
        $buildingAtPlanet = $planet->buildings()->find($bid);

        //resources refund
        $refund = $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($owner, $buildingAtPlanet->pivot->level, $buildingAtPlanet, $planetId);

        $this->buy($planet, $refund);

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'startTime' => null,
            'timeToBuild' => null,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        $planet = Planet::find($planetId);

        $ref = $this->refreshPlanet($request, $planet);

        return response()->json(['status' => 'success',
            'message' => MessagesController::i18n('building_removed_from_que', $language),
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
     * Message if building current level is 0
     * Slots check, resources refund
     *
     * @uses i18n
     * @param $request Request
     * @param int $planetId
     * @param int $buildingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function downgradeBuilding(Request $request, int $planetId, int $buildingId)
    {
        $language = $request->auth->language;

        $planet = Planet::find($planetId);
        if (!$planet)
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('planet_not_found', $language)], 403);
        $user = User::find($planet->owner_id);

        $ref = $this->refreshPlanet($request, $planet);
        //slots check
        if ($ref['buildingQued'] && ($ref['queTimeRemain'] > 0))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_que_not_empty', $language), 'time_remain' => $ref['queTimeRemain']], 403);

        $building = Building::find($buildingId);

        $buildingAtPlanet = $planet->buildings()->where('building_id', $buildingId)->first();
        if (!$buildingAtPlanet || ($buildingAtPlanet->pivot->level <= 0))
            return response()->json(['status' => 'error', 'message' => MessagesController::i18n('building_level_0', $language)], 403);

        //resources refund
        $resourcesAtLevel = app('App\Http\Controllers\ResourceController')
            ->parseAll($user, $building, $buildingAtPlanet->pivot->level, $planetId);

        $this->buy($planet, $resourcesAtLevel, true);

        $timeToBuild = $resourcesAtLevel['cost']['time'];

        $planet->buildings()->updateExistingPivot($buildingAtPlanet->id, [
            'level' => $buildingAtPlanet->pivot->level - 1,
            'destroying' => 1,
            'startTime' => Carbon::now()->format('Y-m-d H:i:s'),
            'timeToBuild' => $timeToBuild,
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

        return response()->json(['status' => 'success',
            'level' => $buildingAtPlanet->pivot->level - 1,
            'timeToBuild' => $timeToBuild
        ], 200);
    }
}
